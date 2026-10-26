# SmileSuite business proposal

**Prepared by:** Kenji B. Rique and Samuael D. Luz  
**Program / Year / Section:** BSIT III F1  
**Courses:** Web Systems and Technologies 2; System Integration and Architecture 1  
**Date:** 27 October 2026

## Business problem

An independent dental clinic must coordinate patient requests, practitioner availability, treatment chairs, intake, and appointment updates. When these activities are handled in separate calendars, messages, and files, staff must repeatedly enter information and reconcile changes. A clinic also needs a public place to explain its services without exposing patient information. These are the operational problems SmileSuite is designed to address; this proposal does not claim survey results or measured savings.

## Proposed business and users

SmileSuite is a business-to-business software subscription for independent dental clinics. A clinic receives its own subdomain, tenant-local accounts, scheduling workspace, patient portal, and content scope. The initial scope is one physical location per clinic tenant, with one optional clinic-owned Shopify connection.

| User / customer | Need | Proposed value |
| --- | --- | --- |
| Clinic owner, the subscription customer | Coordinate the clinic and control staff access | One workspace for configuration, appointments, reports, and subscription status |
| Receptionist | Register walk-ins and manage appointment changes | Resource-aware scheduling, operational views, and aggregate reports |
| Practitioner | Find assigned visits and relevant care information | Authorized access to versioned intake and encounter notes |
| Patient | Request a visit and supply intake information | Clinic-specific account, booking, own appointments, intake history, and profile management |
| Platform administrator | Operate the subscription service | Separate tenant/lifecycle and billing metadata administration |
| Content editor | Maintain public information | Directus editor with a platform or clinic-specific content scope |

## Product and value proposition

The major modules are clinic/identity management, scheduling, clinical intake and notes, billing, optional commerce, content management, and operational reporting. Scheduling uses clinic hours, practitioner shifts and breaks, dated blocks, eligible services, and available chairs. Tenant ownership and resource authorization apply to each clinic's records.

Directus 11.17.4 supplies an actual CMS editor and HTTP API for pages, announcements, service descriptions, a safe staff directory, and aggregate reports. Laravel retains ownership of authentication, operational scheduling, patient records, and clinical authorization. The CMS receives staff display names/roles and summary counts/scheduled value; it receives no patient details, clinical text, or application credential hashes.

The intended benefit is less coordination work and clearer patient access. Those benefits must be evaluated with real users before being reported as outcomes. The current deliverable is a local prototype using synthetic Harbor and Cedar clinics.

## Revenue and operating model

The proposed revenue source is a recurring clinic-to-platform SaaS subscription. Commercial tiers may reflect practitioner and chair limits, but actual prices, billing currency, taxes, trials, and final entitlements require the business owner's approval. Seeded prices are demonstration fixtures, not a published offer.

Patients pay appointment charges directly at the clinic in the MVP. If a clinic connects Shopify, that clinic is the merchant and manages its catalog, payment arrangements, fulfillment, returns, and customer account. SmileSuite does not warehouse goods or claim clinic retail proceeds as platform revenue. Reports show scheduled appointment value, which is not collected revenue.

Expected cost categories include application/database hosting, backups and storage, email delivery, domain/TLS administration, provider charges, maintenance, and support. No cost estimates or profit forecast are established because provider choices, workload, and pricing are not yet approved.

## Delivery boundary and viability decisions

The coursework demonstration covers synthetic accounts, booking, intake, reports, profile changes, and local CMS integration. Stripe and Shopify implementations have fixture-based development paths; live transactions and authorized merchant-store evidence are separate release work. Custom domains, SMS, online clinical deposits, multi-location groups, imaging, prescriptions, and integrated purchase history remain outside the MVP.

Before a real pilot, resolve commercial scope, applicable privacy/clinical obligations, retention/access rules, provider eligibility, hosting and recovery, and accountable operational owners. The decision register in [the revised system plan](../smilesuite_system_plan_v2.md#12-decision-register-and-launch-gates) records those gates. This proposal makes no production-readiness, regulatory-approval, delivery-date, or financial-performance claim.

Related deliverables: [SRS](SRS.md), [system design](SYSTEM_DESIGN.md), [database schema](DATABASE_SCHEMA.md), and [demonstration guide](DEMO_GUIDE.md).
