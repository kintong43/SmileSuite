import { randomBytes } from 'node:crypto';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

const base = new URL(process.env.CMS_URL || 'http://127.0.0.1:8055');
if (base.username || base.password || base.search || base.hash || !(base.protocol === 'https:' || (base.protocol === 'http:' && ['127.0.0.1', 'localhost'].includes(base.hostname)))) throw new Error('CMS_URL must use HTTPS or local HTTP.');
const scopes = JSON.parse(process.env.CMS_TENANTS_JSON || '["platform","clinic-harbor","clinic-cedar"]');
if (!Array.isArray(scopes) || !scopes.includes('platform') || scopes.some(scope => typeof scope !== 'string' || !(scope === 'platform' || /^clinic-[a-z0-9][a-z0-9-]{0,62}$/.test(scope)))) throw new Error('Invalid CMS_TENANTS_JSON. Include platform and clinic-prefixed subdomains.');
if (!process.env.CMS_ADMIN_EMAIL || !process.env.CMS_ADMIN_PASSWORD) throw new Error('Set CMS_ADMIN_EMAIL and CMS_ADMIN_PASSWORD for bootstrap only.');
let bearer;
async function api(path, method = 'GET', body) {
  const response = await fetch(new URL(path, base), { method, redirect: 'error', headers: { 'Content-Type': 'application/json', ...(bearer ? { Authorization: `Bearer ${bearer}` } : {}) }, ...(body === undefined ? {} : { body: JSON.stringify(body) }) });
  if (!response.ok) throw new Error(`Directus ${method} ${path.split('?')[0]} failed (${response.status}). Check local Directus logs; credentials are not printed.`);
  return response.status === 204 ? null : (await response.json()).data;
}
bearer = (await api('/auth/login', 'POST', { email: process.env.CMS_ADMIN_EMAIL, password: process.env.CMS_ADMIN_PASSWORD })).access_token;
const credentialsPath = resolve('.runtime/cms-credentials.json');
let credentials = { editors: {}, tokens: {} };
try { credentials = JSON.parse(await readFile(credentialsPath, 'utf8')); } catch (error) { if (error.code !== 'ENOENT') throw error; }
await mkdir(resolve('.runtime'), { recursive: true });
const saveCredentials = () => writeFile(credentialsPath, JSON.stringify(credentials, null, 2), { mode: 0o600 });
const textField = (field, type = 'string', extra = {}) => ({ field, type, meta: { interface: type === 'text' ? 'input-multiline' : type === 'json' ? 'input-code' : 'input', ...extra }, schema: { is_nullable: true } });
const content = [textField('title'), textField('body', 'text'), { ...textField('status', 'string', { interface: 'select-dropdown', options: { choices: [{ text: 'Draft', value: 'draft' }, { text: 'Published', value: 'published' }] } }), schema: { default_value: 'draft', is_nullable: false } }];
const definitions = {
  ss_pages: [textField('slug'), ...content],
  ss_announcements: [...content, textField('publish_from', 'timestamp'), textField('publish_to', 'timestamp')],
  ss_services: [...content, textField('service_ref')],
  ss_directory: [textField('display_name'), textField('roles', 'json'), textField('resource_id')],
  ss_reports: [textField('period'), textField('aggregates', 'json')],
};
const existing = new Set((await api('/collections')).map(row => row.collection));
for (const [collection, fields] of Object.entries(definitions)) {
  if (!existing.has(collection)) await api('/collections', 'POST', { collection, meta: { icon: collection === 'ss_reports' ? 'bar_chart' : 'article', note: 'SmileSuite tenant-scoped content. Never enter patient records or credentials.' }, schema: {}, fields: [{ field: 'id', type: 'uuid', meta: { hidden: true, readonly: true, special: ['uuid'] }, schema: { is_primary_key: true, is_nullable: false } }, { field: 'tenant_key', type: 'string', meta: { hidden: true, readonly: true }, schema: { is_nullable: false } }, ...fields] });
}
const query = filter => new URLSearchParams({ filter: JSON.stringify(filter), limit: '500' }).toString();
for (const scope of scopes) {
  const roleName = `SmileSuite ${scope} editor`;
  let role = (await api(`/roles?${query({ name: { _eq: roleName } })}`))[0];
  if (!role) role = await api('/roles', 'POST', { name: roleName, icon: 'edit', description: `Content for ${scope} only` });
  let policy = (await api(`/policies?${query({ name: { _eq: roleName } })}`))[0];
  if (!policy) policy = await api('/policies', 'POST', { name: roleName, icon: 'edit', admin_access: false, app_access: true, roles: [{ role: role.id }] });
  const permissions = await api(`/permissions?${query({ policy: { _eq: policy.id } })}`);
  for (const [collection, fields] of Object.entries(definitions)) {
    const names = ['id', 'tenant_key', ...fields.map(field => field.field)];
    for (const action of ['create', 'read', 'update', 'delete']) {
      const mutable = names.filter(field => !['id', 'tenant_key'].includes(field));
      const body = { policy: policy.id, collection, action, permissions: { tenant_key: { _eq: scope } }, validation: { tenant_key: { _eq: scope } }, presets: action === 'create' ? { tenant_key: scope } : {}, fields: ['read', 'delete'].includes(action) ? names : mutable };
      const permission = permissions.find(row => row.collection === collection && row.action === action);
      await api(permission ? `/permissions/${permission.id}` : '/permissions', permission ? 'PATCH' : 'POST', body);
    }
  }
  // Data Studio uses read-only schema metadata; no user-list or global-settings access is granted.
  for (const collection of ['directus_collections', 'directus_fields', 'directus_relations']) {
    const permission = permissions.find(row => row.collection === collection && row.action === 'read');
    const body = { policy: policy.id, collection, action: 'read', permissions: { collection: { _in: Object.keys(definitions) } }, fields: ['*'] };
    await api(permission ? `/permissions/${permission.id}` : '/permissions', permission ? 'PATCH' : 'POST', body);
  }
  const email = `${scope}-editor@example.com`;
  let editor = (await api(`/users?${query({ email: { _eq: email } })}`))[0];
  if (!editor) {
    const password = randomBytes(24).toString('base64url');
    editor = await api('/users', 'POST', { email, password, first_name: scope, last_name: 'Content Editor', role: role.id, status: 'active' });
    credentials.editors[scope] = { email, password };
    await saveCredentials();
  }
  const apiEmail = `${scope}-api@example.com`;
  let apiUser = (await api(`/users?${query({ email: { _eq: apiEmail } })}`))[0];
  if (!apiUser) {
    const token = randomBytes(32).toString('hex');
    apiUser = await api('/users', 'POST', { email: apiEmail, password: randomBytes(32).toString('base64url'), first_name: scope, last_name: 'Server Integration', role: role.id, status: 'active', token });
    credentials.tokens[scope] = token;
    await saveCredentials();
  }
  const pages = [
    { slug: 'about', title: scope === 'platform' ? 'About SmileSuite' : `Welcome to ${scope}`, body: 'SmileSuite connects clinic scheduling, patient access, services and clinic-owned storefronts. Each clinic manages its own workspace and public content.' },
    { slug: 'features', title: 'Features & services', body: 'Plan appointments, coordinate clinic resources, manage patient intake, send appointment updates and connect a clinic-owned store. Clinic staff control access to records. Service descriptions here are informational; booking terms remain in SmileSuite.' },
  ];
  for (const page of pages) {
    const found = await api(`/items/ss_pages?${query({ tenant_key: { _eq: scope }, slug: { _eq: page.slug } })}`);
    if (!found.length) await api('/items/ss_pages', 'POST', { tenant_key: scope, ...page, status: 'published' });
  }
  const announcements = await api(`/items/ss_announcements?${query({ tenant_key: { _eq: scope } })}`);
  if (!announcements.length) await api('/items/ss_announcements', 'POST', { tenant_key: scope, title: 'Welcome to our connected clinic', body: 'Check this page for clinic announcements and service information.', status: 'published' });
}
await mkdir(resolve('.runtime'), { recursive: true });
await writeFile(credentialsPath, JSON.stringify(credentials, null, 2), { mode: 0o600 });
console.log('CMS collections, tenant policies and starter content are ready. Private credentials saved to ignored .runtime/cms-credentials.json. Set CMS_TOKENS_JSON from its tokens object; never commit this file.');
