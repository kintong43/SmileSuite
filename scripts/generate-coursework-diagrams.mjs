/**
 * Rebuild coursework figures from the committed, schema-only PostgreSQL dump.
 * No database connections or mutations are performed by this renderer.
 * Optional isolated renderer setup (does not change application dependencies):
 * npm install --prefix .runtime/document-tools --no-save @viz-js/viz@3.31.0
 * The project's installed @playwright/test supplies Chromium PNG rendering.
 * Usage: node scripts/generate-coursework-diagrams.mjs
 */
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';
import { chromium } from '@playwright/test';

const root = fileURLToPath(new URL('../', import.meta.url));
const out = path.join(root, 'docs/coursework/diagrams');
const { instance } = await import(pathToFileURL(path.join(root, '.runtime/document-tools/node_modules/@viz-js/viz/dist/viz.js')));
const viz = await instance();
for (const folder of ['src', 'svg', 'png', 'text']) {
    await mkdir(path.join(out, folder), { recursive: true });
}
const save = (folder, name, value) => writeFile(path.join(out, folder, name), value);
const xml = value => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
const sql = await readFile(path.join(out, 'src/schema.sql'), 'utf8');
const schema = { source: 'pg_dump --schema-only --no-owner --no-privileges; PostgreSQL 16.15; local smilesuite; 2026-10-27', tables: [], relationships: [] };
const list = text => text.split(',').map(part => part.trim());
for (const match of sql.matchAll(/CREATE TABLE public\.(\w+) \(\r?\n([\s\S]*?)\r?\n\);/g)) {
    const table = { name: match[1], columns: [], constraints: [], primaryKey: [], uniqueKeys: [] };
    for (const raw of match[2].split(/\r?\n/)) {
        const line = raw.trim().replace(/,$/, '');
        if (line.startsWith('CONSTRAINT ')) { table.constraints.push(line); continue; }
        const column = line.match(/^(\w+) (.+)$/);
        if (!column) { throw new Error(`Cannot parse ${table.name}: ${line}`); }
        const definition = column[2];
        const defaultMatch = definition.match(/ DEFAULT ([\s\S]*?)(?: NOT NULL)?$/);
        table.columns.push({ name: column[1], type: definition.split(/ DEFAULT | NOT NULL/)[0], nullable: !definition.endsWith('NOT NULL'), default: defaultMatch?.[1] ?? null });
    }
    schema.tables.push(table);
}
const byName = new Map(schema.tables.map(table => [table.name, table]));
for (const match of sql.matchAll(/ALTER TABLE ONLY public\.(\w+)\r?\n\s+ADD CONSTRAINT (\w+) ([\s\S]*?);/g)) {
    const table = byName.get(match[1]);
    table.constraints.push(`${match[2]} ${match[3]}`);
    const pk = match[3].match(/^PRIMARY KEY \(([^)]+)\)/);
    if (pk) { table.primaryKey = list(pk[1]); }
    const unique = match[3].match(/^UNIQUE \(([^)]+)\)/);
    if (unique) { table.uniqueKeys.push(list(unique[1])); }
    const fk = match[3].match(/^FOREIGN KEY \(([^)]+)\) REFERENCES public\.(\w+)\(([^)]+)\)(.*)$/);
    if (fk) {
        schema.relationships.push({ name: match[2], child: table.name, columns: list(fk[1]), parent: fk[2], references: list(fk[3]), deleteRule: fk[4].trim() || 'NO ACTION' });
    }
}
for (const match of sql.matchAll(/ALTER TABLE ONLY public\.(\w+) ALTER COLUMN (\w+) SET DEFAULT (.*);/g)) {
    byName.get(match[1]).columns.find(column => column.name === match[2]).default = match[3];
}
if (schema.tables.length < 5) { throw new Error('Expected at least five actual tables.'); }
for (const table of schema.tables) {
    if (!table.primaryKey.length) { throw new Error(`No primary key: ${table.name}`); }
}
for (const fk of schema.relationships) {
    const child = byName.get(fk.child), parent = byName.get(fk.parent);
    if (!parent || fk.columns.length !== fk.references.length) { throw new Error(`Invalid FK: ${fk.name}`); }
    for (let index = 0; index < fk.columns.length; index++) {
        if (child.columns.find(c => c.name === fk.columns[index])?.type !== parent.columns.find(c => c.name === fk.references[index])?.type) { throw new Error(`FK type mismatch: ${fk.name}`); }
    }
    if (![parent.primaryKey, ...parent.uniqueKeys].some(key => JSON.stringify(key) === JSON.stringify(fk.references))) { throw new Error(`FK target is not unique: ${fk.name}`); }
    fk.optionalParent = fk.columns.some(name => child.columns.find(c => c.name === name).nullable);
    fk.uniqueChild = [child.primaryKey, ...child.uniqueKeys].some(key => key.every(name => fk.columns.includes(name)));
}
await save('src', 'erd.json', JSON.stringify(schema, null, 2) + '\n');
const purposes = {
    tenants:'Clinic registry, subdomain, timezone, booking policy and billing customer identifiers.', users:'Tenant-local credentials and identity; password is a Laravel hash.', user_roles:'Allowlisted tenant-local roles; one user may hold several roles.', patient_profiles:'Patient contact details; account link is optional for walk-ins.', practitioners:'Clinical practitioners linked to clinic users.', staff_invitations:'Expiring staff invitation hashes and proposed roles.', platform_admins:'Separate platform credentials and MFA material.', platform_audit_events:'Append-only platform administration actions.', services:'Clinic service duration, buffer, currency and fee configuration.', operatories:'Clinic chair inventory.', practitioner_services:'Practitioner/service eligibility junction.', operatory_services:'Chair/service compatibility junction.', clinic_hours:'Recurring opening windows.', practitioner_shifts:'Recurring practitioner working windows.', practitioner_breaks:'Recurring practitioner unavailable windows.', schedule_blocks:'Dated clinic closures, practitioner leave or chair maintenance.', appointments:'Reservations with UTC instants, status, historical price/duration/buffer snapshots.', appointment_events:'Append-only appointment state and version history.', idempotency_requests:'Actor-scoped booking replay protection.', intake_submissions:'Versioned encrypted patient intake.', clinical_notes:'Versioned encrypted practitioner notes.', care_assignments:'Explicit, time-limited clinical access grants.', consent_records:'Purpose-specific consent and withdrawal evidence.', audit_events:'Tenant-scoped operational and clinical access audit metadata.', subscriptions:'Tenant-billable Cashier subscription state.', subscription_items:'Provider price/product items for a subscription.', billing_entitlements:'Derived server-enforced clinic plan limits.', billing_checkout_sessions:'Idempotent provider checkout references.', webhook_events:'Durable verified provider event inbox.', shopify_connections:'Clinic shop domain, encrypted Storefront credentials and connection generation.', shopping_carts:'Clinic/session-bound provider cart references.', outbox_messages:'Transactional appointment notification work.', notification_deliveries:'Delivery attempts and status for outbox work.', sessions:'Framework database session storage; local session driver is file.', password_reset_tokens:'Tenant-bound hashed password reset credentials.', jobs:'Framework database queue work.', job_batches:'Framework queue batch metadata.', failed_jobs:'Framework failed queue work.', cache:'Framework database cache entries; local cache driver is file.', cache_locks:'Framework database cache locks.', migrations:'Applied Laravel migration ledger.'
};
const fieldDescription = (table, column) => {
    if (column.name === 'password') { return 'One-way password hash; never plaintext.'; }
    if (/secret|token|answers|content/.test(column.name)) { return 'Protected application field; inspect model cast/service for hash or encryption handling.'; }
    if (column.name === 'tenant_id') { return 'Clinic ownership boundary; assigned from trusted tenant context.'; }
    if (column.name === 'id') { return 'Record primary identifier.'; }
    if (column.name.endsWith('_id')) { return 'Related record identifier; see FK register for enforced references.'; }
    if (/created_at|updated_at/.test(column.name)) { return 'Framework record timestamp.'; }
    return column.name.replaceAll('_', ' ') + '; physical definition preserved from PostgreSQL.';
};
let dictionary = '# Physical data dictionary\n\nSource: schema-only export from PostgreSQL 16.15, database `smilesuite`, 27 October 2026. No records, credentials, or patient values are exported. Defaults below are schema definitions.\n\n';
for (const table of schema.tables) {
    dictionary += `## ${table.name}\n\n${purposes[table.name] ?? 'Application persistence table.'}\n\n| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |\n| --- | --- | --- | --- | --- | --- |\n`;
    for (const column of table.columns) {
        const keys = [table.primaryKey.includes(column.name) ? 'PK' : '', schema.relationships.some(fk => fk.child === table.name && fk.columns.includes(column.name)) ? 'FK' : ''].filter(Boolean).join(', ');
        dictionary += `| ${column.name} | ${column.type} | ${keys || '—'} | ${column.nullable ? 'Yes' : 'No'} | ${(column.default ?? '—').replaceAll('|','&#124;')} | ${fieldDescription(table,column)} |\n`;
    }
    dictionary += '\n';
}
dictionary += '## Enforced foreign keys\n\n| Constraint | Child columns | Parent key | Delete behavior |\n| --- | --- | --- | --- |\n';
for (const fk of schema.relationships) { dictionary += `| ${fk.name} | ${fk.child} (${fk.columns.join(', ')}) | ${fk.parent} (${fk.references.join(', ')}) | ${fk.deleteRule} |\n`; }
await save('text','data-dictionary.md',dictionary);
let allMermaid = 'erDiagram\n';
for (const table of schema.tables) { allMermaid += `    ${table.name} {\n${table.columns.map(c => `        ${c.type.replace(/[^a-zA-Z0-9]/g,'_')} ${c.name}${table.primaryKey.includes(c.name) ? ' PK' : ''}`).join('\n')}\n    }\n`; }
for (const fk of schema.relationships) { allMermaid += `    ${fk.parent} ${fk.optionalParent ? '|o' : '||'}--${fk.uniqueChild ? 'o|' : 'o{'} ${fk.child} : "${fk.columns.join('+')}"\n`; }
await save('src','erd-full.mmd',allMermaid);

const figures = [];
const frame = (title, subtitle, body, height=760) => `<svg xmlns="http://www.w3.org/2000/svg" width="864" height="${height}" viewBox="0 0 864 ${height}" role="img" aria-label="${xml(title)}"><rect width="864" height="${height}" fill="white"/><style>text{font-family:Arial,sans-serif;fill:#222} .small{font-size:13px} .body{font-size:16px} .heading{font-size:25px;font-weight:700} .line{fill:none;stroke:#444;stroke-width:1.6}</style><defs><marker id="arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8" fill="none" stroke="#444" stroke-width="1.2"/></marker></defs><text x="32" y="40" class="heading">${xml(title)}</text><text x="32" y="65" class="small">${xml(subtitle)}</text>${body}</svg>`;
const text = (x,y,value,cls='body',anchor='start') => `<text x="${x}" y="${y}" class="${cls}" text-anchor="${anchor}">${xml(value)}</text>`;
const lines = (x,y,values,cls='body',anchor='start') => values.map((value,index)=>text(x,y+index*22,value,cls,anchor)).join('');
const box = (x,y,w,h,values,fill='#f3f3f3') => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="4" fill="${fill}" stroke="#444" stroke-width="1.6"/>${lines(x+w/2,y+28,values,'body','middle')}`;
const arrow = (points,label,x,y) => `<polyline points="${points}" class="line" marker-end="url(#arrow)"/>${label ? `<rect x="${x-3}" y="${y-15}" width="${label.length*7.1+8}" height="21" fill="white"/>${text(x,y,label,'small')}` : ''}`;
async function svgFigure(name,svg,caption,mermaid='') { await save('svg',name+'.svg',svg); if(mermaid) { await save('src',name+'.mmd',mermaid); } figures.push({name,caption}); }
const plates = [
    ['erd-core','Identity and clinic ownership',['tenants','users','user_roles','patient_profiles','practitioners','staff_invitations']],
    ['erd-scheduling-resources','Services and scheduling resources',['services','operatories','practitioners','practitioner_services','operatory_services']],
    ['erd-scheduling-calendar','Recurring and dated availability',['tenants','practitioners','operatories','clinic_hours','practitioner_shifts','practitioner_breaks','schedule_blocks']],
    ['erd-scheduling-bookings','Bookings and replay protection',['appointments','patient_profiles','practitioners','services','operatories','appointment_events','idempotency_requests']],
    ['erd-clinical','Clinical history and access',['patient_profiles','appointments','intake_submissions','clinical_notes','care_assignments','consent_records']],
    ['erd-integrations-billing','Subscription and provider inbox',['tenants','subscriptions','subscription_items','billing_entitlements','billing_checkout_sessions','webhook_events']],
    ['erd-integrations-delivery','Commerce and notification delivery',['tenants','shopify_connections','shopping_carts','appointments','outbox_messages','notification_deliveries']],
    ['erd-platform','Platform and operational audit',['platform_admins','platform_audit_events','tenants','users','audit_events','password_reset_tokens']]
];
const essential = {appointments:['id','tenant_id','patient_id','practitioner_id','operatory_id','service_id','status'],tenants:['id','subdomain','timezone'],users:['id','tenant_id','email'],patient_profiles:['id','tenant_id','user_id'],practitioners:['id','tenant_id','user_id'],intake_submissions:['id','tenant_id','patient_id','appointment_id','supersedes_id'],clinical_notes:['id','tenant_id','patient_id','appointment_id','practitioner_id','supersedes_id']};
for (const [name,title,names] of plates) {
    const rels = schema.relationships.filter(fk=>names.includes(fk.child)&&names.includes(fk.parent)&&!(fk.parent==='tenants'&&name!=='erd-core'&&name!=='erd-scheduling-calendar'&&name!=='erd-integrations-billing'&&name!=='erd-integrations-delivery'));
    let dot='digraph G { graph [rankdir=LR, bgcolor="white", nodesep=0.36, ranksep=0.52, pad=0.12, splines=polyline]; node [shape=plain,fontname="Arial",fontsize=13]; edge [color="#555555",penwidth=1.1,arrowsize=0.8];\n';
    for (const name of names) {
        const table=byName.get(name);
        const fields=essential[name]??table.columns.filter(c=>table.primaryKey.includes(c.name)||c.name.endsWith('_id')).map(c=>c.name).slice(0,7);
        dot+=`"${name}" [label=<<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" CELLPADDING="5"><TR><TD BGCOLOR="#dddddd" COLSPAN="2"><B>${name}</B></TD></TR>`;
        for (const field of fields) {
            const c=table.columns.find(c=>c.name===field);
            const mark=[table.primaryKey.includes(field)?'PK':'',schema.relationships.some(fk=>fk.child===name&&fk.columns.includes(field))?'FK':''].filter(Boolean).join('/');
            dot+=`<TR><TD ALIGN="LEFT" PORT="${field}">${field}${c.nullable?' ?':''}</TD><TD ALIGN="RIGHT">${mark}</TD></TR>`;
        }
        dot+='</TABLE>>];\n';
    }
    for(const fk of rels) { dot+=`"${fk.parent}" -> "${fk.child}" [dir=both, arrowtail="${fk.optionalParent?'teeodot':'teetee'}",arrowhead="${fk.uniqueChild?'teeodot':'crowodot'}"];\n`; }
    dot+='}\n';
    await save('src',name+'.dot',dot);
    const rendered=viz.renderString(dot,{format:'svg'});
    const vb=rendered.match(/viewBox="([^"]+)"/)[1].split(' ').map(Number);
    const content=rendered.slice(rendered.indexOf('<g '),rendered.lastIndexOf('</svg>'));
    const scale=Math.min(800/vb[2],550/vb[3]);
    const body=`<svg x="32" y="92" width="800" height="550" viewBox="${vb.join(' ')}">${content}</svg>${text(32,675,'Key projection: full types, attributes and every composite FK are in the dictionary.','small')}${text(32,699,'PK = primary key; FK = foreign key; ? = nullable; circle = optional; crow = many.','small')}${text(32,723,'Tenant ownership edges outside this module are omitted; no relationship is inferred.','small')}`;
    await svgFigure(name,frame('ERD · '+title,'PostgreSQL 16.15 · crow’s foot · physical keys; module projection',body),title, 'erDiagram\n'+rels.map(fk=>`    ${fk.parent} ${fk.optionalParent?'|o':'||'}--${fk.uniqueChild?'o|':'o{'} ${fk.child} : "${fk.columns.join('+')}"`).join('\n')+'\n');
    if(scale<0.65) { console.warn(`${name}: dense projection; inspect SVG before printing`); }
}

const contextSpec={system:'SmileSuite',processes:[{id:'0',name:'SmileSuite'}],stores:[],entities:['Patient','Clinic staff','Clinic owner','Platform administrator','Directus CMS','Stripe / Shopify','Email delivery'],flows:[['Patient','0','Account, booking and intake details'],['0','Patient','Appointments, content and status'],['Clinic staff','0','Schedule and care updates'],['0','Clinic staff','Permitted operational / care views'],['Clinic owner','0','Clinic and subscription settings'],['0','Clinic owner','Reports and configuration status'],['Platform administrator','0','Tenant administration requests'],['0','Platform administrator','Operational metadata'],['Directus CMS','0','Published tenant content'],['0','Directus CMS','Staff directory / aggregate projections'],['Stripe / Shopify','0','Provider events / catalog responses'],['0','Stripe / Shopify','Subscription / cart requests'],['0','Email delivery','Minimal appointment notices'],['Email delivery','0','Delivery result']]};
if(contextSpec.processes.length!==1||contextSpec.stores.length) { throw new Error('Invalid context level.'); }
await save('src','context.json',JSON.stringify(contextSpec,null,2)+'\n');
let ctx=box(310,280,244,120,['0','SmileSuite','Clinic platform']);
ctx+=box(32,110,230,74,['Patient']);ctx+=arrow('262,150 330,280','Account / booking / intake',270,205)+arrow('310,312 160,184','Appointments / status',40,225);
ctx+=box(32,310,230,74,['Clinic staff']);ctx+=arrow('262,330 310,330','Schedule / care updates',35,300)+arrow('310,365 262,365','Permitted views',60,410);
ctx+=box(32,530,230,74,['Clinic owner']);ctx+=arrow('262,550 340,400','Clinic / billing settings',45,475)+arrow('370,400 230,530','Reports / status',265,520);
ctx+=box(602,110,230,74,['Platform administrator']);ctx+=arrow('602,150 535,280','Administration requests',570,205)+arrow('554,310 720,184','Operational metadata',635,235);
ctx+=box(602,310,230,74,['Directus CMS']);ctx+=arrow('602,330 554,330','Published content',620,300)+arrow('554,366 602,366','Safe projections',640,410);
ctx+=box(602,530,230,74,['Stripe / Shopify','Disabled live gates']);ctx+=arrow('602,550 535,400','Events / catalog',610,470)+arrow('500,400 630,530','Billing / cart requests',405,525);
ctx+=box(310,610,244,74,['Email delivery']);ctx+=arrow('405,400 405,610','Minimal notices',275,580)+arrow('470,610 470,400','Delivery result',485,580);
await svgFigure('context',frame('System context','Single process · external entities · labeled data flows · no database detail',ctx),'Context diagram of SmileSuite','flowchart LR\n Patient[Patient] -->|Account, booking, intake| App((0 SmileSuite))\n App -->|Appointments, content, status| Patient\n Staff[Clinic staff] -->|Schedule and care updates| App\n App -->|Permitted views| Staff\n Owner[Clinic owner] -->|Configuration| App\n App -->|Reports and status| Owner\n Admin[Platform administrator] -->|Administration requests| App\n App -->|Operational metadata| Admin\n CMS[Directus CMS] -->|Published tenant content| App\n App -->|Safe projections| CMS\n Provider[Stripe / Shopify] -->|Events and catalog| App\n App -->|Billing and cart requests| Provider\n App -->|Minimal notices| Mail[Email delivery]\n Mail -->|Delivery result| App\n');

const arch={components:[{id:'browser',name:'Browser',technology:'Blade / Tailwind / Alpine'},{id:'laravel',name:'Laravel application',technology:'Laravel 13 / PHP 8.4'},{id:'db',name:'Application database',technology:'PostgreSQL 16.15; smilesuite'},{id:'directus',name:'CMS API and editor',technology:'Directus 11.17.4'},{id:'cmsdb',name:'CMS database',technology:'Separate PostgreSQL database'},{id:'worker',name:'Queue / outbox worker',technology:'Laravel; database queue locally'},{id:'providers',name:'External providers',technology:'Stripe / Shopify / mail'}],connections:[['browser','laravel','HTTP locally; HTTPS deployment','Pages / forms / JSON'],['laravel','db','PDO PostgreSQL','Tenant records'],['laravel','directus','Server HTTP API','Tenant-scoped token / published content / safe projections'],['directus','cmsdb','PostgreSQL','Five content/projection collections'],['laravel','worker','Database queue','IDs / outbox work'],['worker','db','PDO PostgreSQL','Outbox / delivery state'],['laravel','providers','HTTPS','Billing / storefront contracts'],['worker','providers','Configured mail transport','Minimal notices']]};
for(const [from,to,protocol,data] of arch.connections) { if(!arch.components.some(c=>c.id===from)||!arch.components.some(c=>c.id===to)||!protocol||!data) {throw new Error('Invalid architecture edge.');} }
await save('src','architecture.json',JSON.stringify(arch,null,2)+'\n');
let a=box(32,112,800,65,['Browser · Blade / Tailwind / Alpine']);
a+=box(32,235,390,120,['Laravel 13 · PHP 8.4','Host → tenant context → auth / MFA','Policies → domain services']);
a+=box(482,235,350,120,['Directus 11.17.4','Separate CMS login / permissions','Content API; local proof recorded separately']);
a+=box(32,428,390,100,['PostgreSQL 16.15 · smilesuite','Clinic / scheduling / clinical records','Sessions: file · cache: file locally']);
a+=box(482,428,350,100,['PostgreSQL · separate CMS database','Pages / announcements / services','Minimal staff / aggregate reports']);
a+=box(32,612,390,85,['Queue + transactional outbox','Database queue locally; mail log']);
a+=box(482,612,350,85,['Stripe / Shopify / email','Live billing / commerce disabled']);
a+=arrow('225,177 225,235','HTTP local / HTTPS deployment',240,208)+arrow('422,285 482,285','Server API',427,270)+arrow('225,355 225,428','PDO / tenant constraints',55,395)+arrow('657,355 657,428','PostgreSQL',667,395)+arrow('125,528 125,612','Outbox IDs',140,573)+arrow('422,657 482,657','Delivery transport',430,640);
a+=text(32,737,'Patient records stay in Laravel. Browser never connects to either database. Redis is a deployment option.','small');
await svgFigure('architecture',frame('System architecture','Local baseline and integration boundaries · no browser database access',a,770),'System architecture of SmileSuite','flowchart TB\n Browser[Browser: Blade / Tailwind / Alpine] -->|HTTP local / HTTPS deployment| App[Laravel 13 / PHP 8.4]\n App -->|PDO: trusted tenant context| DB[(PostgreSQL 16: smilesuite)]\n App -->|Server HTTP API: tenant-bound token| CMS[Directus 11.17.4 API]\n CMS --> CMSDB[(Separate PostgreSQL CMS database)]\n App -->|Database queue / outbox| Worker[Laravel worker]\n Worker --> DB\n App -.->|HTTPS; live gates disabled| External[Stripe / Shopify]\n Worker -->|Configured mail transport| Mail[Mail log locally]\n Editor[CMS editor] -->|Separate CMS login| CMS\n');

const usecaseGroups=[
 ['usecase-patient','Patient portal','Patient',[['UC-01','Register account','OBJ-01','FR-01'],['UC-02','Sign in and manage profile','OBJ-01','FR-02'],['UC-03','Book appointment','OBJ-02','FR-03'],['UC-04','Reschedule or cancel visit','OBJ-02','FR-04'],['UC-05','Submit and view own intake','OBJ-03','FR-05'],['UC-06','Browse content and clinic store','OBJ-04','FR-08']]],
 ['usecase-staff','Clinic operations','Clinic staff',[['UC-07','View clinic schedule','OBJ-02','FR-03'],['UC-08','Register walk-in patient','OBJ-02','FR-06'],['UC-09','Record appointment status','OBJ-02','FR-04'],['UC-10','Read assigned clinical records','OBJ-03','FR-05'],['UC-11','Write clinical note amendment','OBJ-03','FR-05'],['UC-12','Review notification delivery','OBJ-02','FR-09']]],
 ['usecase-management','Management and content','Authorized manager',[['UC-13','Configure clinic and staff','OBJ-01','FR-06'],['UC-14','View and export reports','OBJ-04','FR-10'],['UC-15','Manage clinic subscription','OBJ-04','FR-07'],['UC-16','Manage published content','OBJ-04','FR-11'],['UC-17','Publish safe CMS projections','OBJ-04','FR-11'],['UC-18','Administer tenant metadata','OBJ-01','FR-12']]]
];
const ucSpec={system:'SmileSuite',groups:[]};
for(const [name,title,actor,cases] of usecaseGroups) {
    let body=`<rect x="284" y="103" width="540" height="570" fill="none" stroke="#444" stroke-width="1.6"/>${text(554,131,'SmileSuite · '+title,'body','middle')}`;
    body+='<circle cx="112" cy="319" r="18" fill="white" stroke="#444" stroke-width="1.6"/><path d="M112 337 V407 M72 364 H152 M112 407 L79 450 M112 407 L145 450" class="line"/>'+text(112,478,actor,'body','middle');
    for(let index=0;index<cases.length;index++) {
        const [id,label,obj,fr]=cases[index]; const cy=190+index*87;
        body+=`<line x1="152" y1="364" x2="354" y2="${cy}" class="line"/><ellipse cx="574" cy="${cy}" rx="220" ry="32" fill="white" stroke="#444" stroke-width="1.6"/>${text(574,cy-4,id+' · '+label,'body','middle')}${text(574,cy+16,fr+' / '+obj,'small','middle')}`;
    }
    body+=text(32,711,name==='usecase-staff'?'Clinical actions require practitioner role and current assignment. Reception has operational access.':name==='usecase-management'?'Each use case has its own actor permission; see SRS matrix. “Manager” is a diagram grouping, not a role.':'All patient actions use the resolved clinic and ownership checks.','small');
    await svgFigure(name,frame('Use cases · '+title,'UML associations have no arrowheads; ovals show user goals',body),title);
    ucSpec.groups.push({name,actor,usecases:cases.map(([id,label,objective,requirement])=>({id,name:label,objective,requirement}))});
}
await save('src','usecase.json',JSON.stringify(ucSpec,null,2)+'\n');

const screens=[
 {id:'SCR-01',name:'wireframe-login',title:'Sign in',role:'Clinic account',route:'/login',fr:'FR-01 / FR-02',auth:true,heading:'Welcome back.',fields:['Email address','Password'],buttons:['Sign in'],notes:['Forgot password?','Create a patient account','Local demo access: Clinic owner / Reception / Practitioner / Patient']},
 {id:'SCR-02',name:'wireframe-dashboard',title:'Overview',role:'Clinic staff',route:'/dashboard',fr:'FR-03 / FR-09',heading:'A good day for great care.',cards:['Today’s appointments','Patient community','Visits completed','Scheduled value'],table:['Today’s appointments','Patient | Time | Service | Practitioner | Status'],buttons:['New appointment'],notes:['Scheduled value is not collected revenue.']},
 {id:'SCR-03',name:'wireframe-booking',title:'Book an appointment',role:'Clinic account',route:'/appointments/create',fr:'FR-03 / FR-04',heading:'A visit worth making time for.',fields:['Patient','Service','Practitioner','Chair','Preferred date'],buttons:['Confirm appointment'],notes:['MAKE IT YOUR TIME · clinic timezone','Empty state: No slots available for the selected date.','Conflict state: The time filled up. Choose another slot.','Pay directly at the clinic']},
 {id:'SCR-04',name:'wireframe-profile',title:'Profile',role:'Clinic account',route:'/profile',fr:'FR-02',heading:'Your profile',fields:['Name','Email address','Current password','First name / Last name / Phone (linked patient only)'],buttons:['Save profile','Change password'],notes:['Password change: Current password / Password / Confirm password','Email change requires the current password.']},
 {id:'SCR-05',name:'wireframe-reports',title:'Reports',role:'Owner / receptionist',route:'/reports',fr:'FR-10',heading:'Clinic reports',fields:['From date','To date'],cards:['Appointments','Completed visits','Cancellation / no-show counts','Scheduled value by currency'],table:['Status and service summaries','Aggregate counts | Date range | Service'],buttons:['Apply filters','Export CSV'],notes:['No patient names, clinical text or collected-revenue claim.']},
 {id:'SCR-06',name:'wireframe-content',title:'Public content',role:'Public visitor',route:'/about /features /announcements /content/{slug}',fr:'FR-08 / FR-11',public:true,heading:'Published clinic content',cards:['About','Features','Announcements'],table:['Published content','Title | Summary | Body'],buttons:['Sign in','Create a patient account'],notes:['Only published content for the resolved tenant.','CMS unavailable: show an honest unavailable or empty state.']},
 {id:'SCR-07',name:'wireframe-cms',title:'Content & CMS',role:'Clinic owner',route:'/cms',fr:'FR-11',heading:'Content & CMS',cards:['CMS connection status','Public content links','Safe projection status'],table:['CMS collections','Pages | Announcements | Services | Staff directory | Aggregate reports'],buttons:['Open Directus'],notes:['Separate CMS login with tenant-scoped permissions.','Patient records remain in Laravel.']}
];
await save('src','wireframes.json',JSON.stringify({device:'Desktop; 864 px figure abstraction',screens},null,2)+'\n');
for(const screen of screens) {
    let b=`<rect x="32" y="95" width="800" height="610" fill="white" stroke="#444" stroke-width="1.6"/>${box(32,95,800,40,[screen.route],'#ededed')}`;
    const nav=screen.auth||screen.public?0:155, start=56+nav, width=752-nav;
    if(nav) {b+=`<rect x="32" y="135" width="155" height="570" fill="#ededed"/>${lines(48,174,['SmileSuite','Overview','Schedule','Patients','Services','Notifications','Reports','Clinic store','Profile','Content & CMS'],'small')}`;}
    if(screen.public) {b+=text(56,170,'SmileSuite     About     Features     Announcements     Sign in','body');}
    let y=screen.public?215:178;
    b+=text(start,y,screen.heading,'heading'); y+=36;
    if(screen.cards) {const cw=(width-24)/screen.cards.length; for(let i=0;i<screen.cards.length;i++) {b+=box(start+i*(cw+8),y,cw,65,[]); b+=text(start+8+i*(cw+8),y+27,screen.cards[i],'small')+text(start+8+i*(cw+8),y+50,'—','body');} y+=90;}
    if(screen.fields) {for(const label of screen.fields) {b+=text(start,y,label,'small'); b+=`<rect x="${start}" y="${y+8}" width="${width}" height="30" fill="#fafafa" stroke="#888"/>`; y+=63;}}
    if(screen.table) {b+=box(start,y,width,110,[]);b+=`<rect x="${start}" y="${y}" width="${width}" height="34" fill="#e6e6e6"/>`+text(start+10,y+23,screen.table[0],'body')+text(start+10,y+55,screen.table[1],'small');for(let k=0;k<2;k++){b+=`<line x1="${start+10}" y1="${y+77+k*18}" x2="${start+width-10}" y2="${y+77+k*18}" stroke="#bbb"/>`;}y+=133;}
    for(const note of screen.notes) {b+=text(start,y,note,'small');y+=22;}
    let bx=start; const buttonY=Math.min(Math.max(y+8,625),652);
    for(const button of screen.buttons) { const bw=Math.max(125,button.length*8+25); b+=`<rect x="${bx}" y="${buttonY}" width="${bw}" height="34" rx="3" fill="#333"/><text x="${bx+bw/2}" y="${buttonY+23}" text-anchor="middle" style="font:14px Arial;fill:white">${xml(button)}</text>`;bx+=bw+10; }
    b+=text(32,738,`${screen.id} · ${screen.role} · ${screen.fr} · Grayscale abstraction; generic values only`,'small');
    await svgFigure(screen.name,frame('Wireframe · '+screen.title,'Desktop layout abstraction based on Blade views and coursework additions',b),screen.title);
}

const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:900,height:1000},deviceScaleFactor:3.125});
for(const figure of figures) {
    await page.goto(pathToFileURL(path.join(out,'svg',figure.name+'.svg')).href);
    const svg=page.locator('svg').first();
    await svg.screenshot({path:path.join(out,'png',figure.name+'.png')});
}
const contact=await browser.newPage({viewport:{width:1200,height:1000},deviceScaleFactor:1});
await contact.setContent(`<html><body style="margin:0;background:#ddd;font:14px Arial"><main style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:12px">${figures.map(f=>`<section style="background:white"><img src="${pathToFileURL(path.join(out,'png',f.name+'.png')).href}" style="width:100%"><div style="padding:5px">${f.name}</div></section>`).join('')}</main></body></html>`);
// Inline SVG thumbnails avoid file-origin restrictions on the review contact sheet.
await contact.evaluate(async ({names,svg})=>{document.querySelector('main').innerHTML=names.map((name,i)=>`<section style="background:white">${svg[i].replace('<svg ','<svg style="width:100%;height:auto" ')}<div style="padding:5px">${name}</div></section>`).join('');},{names:figures.map(f=>f.name),svg:await Promise.all(figures.map(f=>readFile(path.join(out,'svg',f.name+'.svg'),'utf8')))});
await contact.screenshot({path:path.join(out,'png','contact-sheet.png'),fullPage:true});
await browser.close();
await save('text','figure-index.json',JSON.stringify(figures,null,2)+'\n');
console.log(`Validated ${schema.tables.length} tables and ${schema.relationships.length} physical foreign keys. Rendered ${figures.length} SVG/PNG figures at 300 px/in when placed at 9 inches wide.`);
