# Data residency and hosting

Researched 14 October 2026 (Linear THI-9). This is a working summary, not legal advice. An Ethiopian lawyer should confirm the Article 22 points before we sign bank, telecom or government clients.

## What counts as personal data in Thirty Degres

The data laws below protect information about identifiable people. They do not cover company information.

- **Not personal data:** logos, colours, fonts, guidelines, templates, and product shots with no people in them.
- **Personal data:** user accounts (names, emails, phone numbers), sign-in records, IP addresses, audit and activity logs, two-factor and passkey records, and photos or video showing identifiable people (staff, models, customers).
- **Possibly sensitive:** photos can reveal ethnicity or religion. Ethiopian law treats sensitive data more strictly, so clients should be told not to upload sensitive imagery during the pilot.

## Ethiopia: Proclamation 1321/2024

- **Regulator:** the Ethiopian Communications Authority (ECA). The law has been in force since July 2024, but by late 2025 the ECA had not issued its implementing directives on registration, critical data or transfers. We found none in 2026 either.
- **Storage in Ethiopia (Art. 22):** personal data collected in Ethiopia must be stored on a server in Ethiopia. Two points are not yet confirmed and need the official gazette text:
    - whether a copy in Ethiopia is enough or all of it must be there;
    - which categories the ECA will name as "critical", which may only be processed in Ethiopia.
- **Sending data abroad (Arts. 18 to 21):** allowed to countries with adequate protection, or with explicit informed consent from the person, among other grounds. Sending sensitive data abroad needs ECA approval.
- **Registration (Art. 33):** every controller and processor must register with the ECA. Certificates last two years. The procedure and fees are still waiting on a directive.
- **Penalties:** fines up to 4% of worldwide turnover. Unlawful cross-border transfer is a criminal offence, with 5 to 10 years in prison and/or ETB 200,000 to 600,000.
- **Who it binds:** Thirty Degres hosted only in Frankfurt is arguably outside the law's direct reach. Our Ethiopian clients are bound, though, and will pass the obligation to us by contract. Once Thirty Degres operates in Ethiopia, it is directly in scope.
- **Banks and government:** assume Ethiopian banks will require hosting in Ethiopia and National Bank of Ethiopia sign-off, and that government work will too. We found no published rule that settles this.

## Kenya: Data Protection Act 2019

- **Sending data abroad** is allowed with appropriate safeguards or consent. Each transfer must be recorded, and the record shown to the Data Commissioner on request. There is no advance filing.
- **Must stay in Kenya:** only for certain public-interest sectors (Regulation 26). These are civil registration and ID, elections, public finance, protected computer systems, basic education, and primary and secondary health care. For these, at least one serving copy must be in Kenya. Banking is not on the list, but banks will bring their own Central Bank of Kenya outsourcing terms.
- **Registration with the Data Commissioner** is required once turnover passes KES 5 million or staff reach 10. Fees run from KES 4,000 for small businesses to KES 40,000 for large ones, valid for two years.
- **Penalties:** up to KES 5 million or 1% of turnover, whichever is lower.

## What we do now (pilot on Laravel Cloud, Frankfurt)

1. **Every client** signs a data processing agreement that names Frankfurt as the hosting location for the pilot.
2. **Ethiopian users** give explicit, informed consent to storage outside Ethiopia when they sign up. This is a checkbox with plain wording, recorded with a timestamp (`users.data_transfer_consent_at`). Built in build week 1 (THI-35); every new account must tick it, including invited teammates.
3. **Collect as little personal data as possible:** no ID documents or phone numbers unless needed. Ask clients not to upload sensitive imagery.
4. **Keep a transfer record** for Kenyan clients.
5. **Don't pilot** with Ethiopian banks, telecoms or government, or with Kenyan Regulation 26 sectors, until in-country hosting exists.

## What makes us move a client in-country

- The ECA publishes its critical-data list or registration directive, or starts enforcing Article 22.
- An Ethiopian bank, telecom or government client signs.
- A Kenyan client in a Regulation 26 sector signs.
- Sensitive data turns up in uploaded assets.
- A client's contract requires it.

## How the app supports it

- **One codebase, deployed separately per region:** Frankfurt now, then Addis Ababa, and later Nairobi. Each deployment has its own Postgres database, file storage and queue.
- **Each workspace has a home region.** All of its data lives only there, with no copying between regions, and backups stay in the same country.
- **A small global directory** maps each workspace to its region so sign-in can route people. It holds no personal data beyond what routing needs.
- **Files go through Laravel's `Storage` facade**, so an S3-compatible store in Ethiopia (for example MinIO on our own server) drops in without code changes. CLAUDE.md rule 5 already requires this.

## In-country hosting options

### Ethiopia

None of these publish prices, so ask for quotes. As a rough estimate, budget about USD 500 to 2,000 a month for a managed server, or USD 10,000 to 25,000 upfront for our own hardware in colocation.

| Provider                | What they offer                                                                                                                        |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Wingu Africa (Addis)    | Tier III colocation, home of the ADDIX internet exchange, building a larger campus in the ICT Park                                     |
| Raxio ET1 (Addis)       | Tier III carrier-neutral colocation                                                                                                    |
| Ethio Telecom TeleCloud | Hosted storage, backup and database services paid in birr via telebirr; no self-service virtual servers or S3-style storage advertised |

### Kenya

| Provider                  | What they offer                                                                   |
| ------------------------- | --------------------------------------------------------------------------------- |
| Safaricom with iXAfrica   | Colocation from 2U, cloud and managed services                                    |
| Lineserve (Nairobi)       | Virtual servers from about KES 3,240 a month (2 CPU, 4 GB)                        |
| Africa Data Centres, PAIX | Colocation                                                                        |
| AWS, Azure, Google        | No live Nairobi region yet (AWS Local Zone announced, Azure stalled, Google none) |

## Still to confirm

- The exact Article 22(1) wording, from the official Negarit Gazeta.
- Whether the ECA has issued any directive in 2026.
- National Bank of Ethiopia cloud and outsourcing rules, and Central Bank of Kenya outsourcing rules.
- Real quotes from Wingu Africa, Raxio and Ethio Telecom.

## Sources

- Proclamation 1321/2024 text (third-party copy): https://www.metaappz.com/References/ethiopian_laws/federal/pr_1321_2024/en/txt
- Shega, enforcement status (Nov 2025): https://shega.co/news/one-year-on-ethiopia-s-personal-data-protection-law-faces-a-slow-path-to-enforcement
- Mondaq / 5A Law Firm compliance guide: https://www.mondaq.com/privacy-protection/1766200/ethiopian-personal-data-protection-law-proclamation-13212024-business-compliance-guide
- DLA Piper, Ethiopia and Kenya: https://www.dlapiperdataprotection.com/
- Kenya General Regulations 2021: https://www.odpc.go.ke/wp-content/uploads/2024/03/THE-DATA-PROTECTION-GENERAL-REGULATIONS-2021-1.pdf
- Kenya Registration Regulations 2021: https://www.odpc.go.ke/wp-content/uploads/2024/03/THE-DATA-PROTECTION-REGISTRATION-OF-DATA-CONTROLLERS-AND-DATA-PROCESSORS-REGULATIONS-2021.pdf
- Bowmans on the Kenyan Act: https://bowmanslaw.com/insights/snapshot-analysis-of-the-data-protection-act-2019/
- Wingu Africa Ethiopia: https://www.wingu.africa/markets/ethiopia
- Raxio Ethiopia: https://www.connectingafrica.com/data-centers/raxio-launches-flagship-data-center-in-ethiopia
- Ethio Telecom TeleCloud: https://ethiotelecom.et/?p=517442
- Safaricom and iXAfrica: https://www.safaricom.co.ke/media-center-landing/press-releases/safaricom-and-ixafrica-data-centres-enter-strategic-partnership-to-deliver-kenyas-first-ai-ready-infrastructure-for-enterprise-innovation
- Lineserve pricing: https://www.lineserve.co.ke/linux-vps
- AWS Local Zones: https://aws.amazon.com/about-aws/global-infrastructure/localzones/locations/
