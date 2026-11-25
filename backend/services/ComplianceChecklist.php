<?php
require_once __DIR__ . '/../core/Database.php';

/**
 * ComplianceChecklist evaluates framework checklists (GDPR, ISO 27001, D-mærket)
 * against the actual content of the ITSM system for a given customer.
 *
 * Each item has:
 *  - id, title, description, reference (article/control number)
 *  - category (for grouping within a framework)
 *  - status: covered | partial | outstanding | not_assessed
 *  - evidence: what the system found (human-readable)
 *  - hint: how to close the gap (null if covered)
 *
 * Items that can be auto-evaluated query the DB; items that require
 * external evidence are returned as not_assessed with a hint.
 */
class ComplianceChecklist
{
    /** @return array[] Frameworks with evaluated items */
    public static function evaluate(?int $customerId = null): array
    {
        return [
            self::gdpr($customerId),
            self::iso27001($customerId),
            self::dmartket($customerId),
        ];
    }

    // ───────────────────────── GDPR ─────────────────────────

    private static function gdpr(?int $cid): array
    {
        $items = [];

        // Art. 30 – Records of processing activities
        $items[] = self::item(
            'gdpr_art30',
            'Records of processing activities',
            'Art. 30',
            'Obligatorisk registrering af behandlingsaktiviteter (hvad persondata behandles til, hvor, af hvem).',
            'data protection',
            self::check(
                "SELECT COUNT(*) n FROM csi_register" . ($cid ? ' WHERE customer_id = ?' : ''),
                $cid ? [$cid] : [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n registrering(er) i CSI-modulet"];
                    return ['outstanding', 'Ingen registreringer fundet', 'Opret registreringer i CSI-modulet (CMDB) som beskriver hvilke persondata der behandles, formål, retsgrundlag og opbevaringsperiode.'];
                }
            )
        );

        // Art. 7 – Consent
        $items[] = self::item(
            'gdpr_art7',
            'Consent management',
            'Art. 7',
            'Samtykke skal være dokumenterbart, specifikt og frivilligt. Brugere skal kunne trække samtykke tilbage.',
            'data subject rights',
            self::check(
                "SELECT COUNT(*) n FROM gdpr_consent_log",
                [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n samtykke(r) logget"];
                    $active = (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL");
                    return ['partial', "0 ud af $active aktive brugere har registreret samtykke", 'Indsaml samtykke fra brugere og registrér det i samtykke-loggen (via brugeradministrationen eller en samtykkeformular).'];
                }
            )
        );

        // Art. 5(1)(e) – Storage limitation / retention
        $items[] = self::item(
            'gdpr_art5e',
            'Storage limitation & retention',
            'Art. 5(1)(e)',
            'Data må ikke opbevares længere end nødvendigt. Der skal være definerede opbevaringsperioder og en proces for sletning.',
            'data protection',
            self::check(
                "SELECT COUNT(*) n FROM data_retention_policies WHERE is_active = 1",
                [],
                function ($n) {
                    if ($n === 0) return ['outstanding', 'Ingen aktive retention-politikker', 'Konfigurer retention-politikker i indstillingerne for de dataklasser der behandles (tickets, brugere, e-mail-log, audit-log osv.).'];
                    $types = self::scalar("SELECT COUNT(DISTINCT entity_type) FROM data_retention_policies WHERE is_active = 1");
                    return ['partial', "$n aktiv(e) politik(ker) påvirker $types datatyper", 'Systemet har retention-politikker konfigureret. Sæt en regelmæssig kørsel op (cron) så data faktisk slettes efter udløb, og dokumentér processen.'];
                }
            )
        );

        // Art. 17 – Right to erasure
        $items[] = self::item(
            'gdpr_art17',
            'Right to erasure',
            'Art. 17',
            'Brugere har ret til at få deres data slettet. Systemet skal understøtte en sletningsproces.',
            'data subject rights',
            self::check(
                "SELECT COUNT(*) n FROM erasure_requests",
                [],
                function ($n) {
                    // Soft-delete exists in the system
                    return ['partial', "$n sletningsanmodning(er) behandlet", 'Systemet understøtter soft-delete af brugere (anonymisering). Dokumentér den fulde sletningsproces inkl. backup-sletning og opret evt. et workflow for sletningsanmodninger.'];
                }
            )
        );

        // Art. 32 – Security of processing (audit trail)
        $items[] = self::item(
            'gdpr_art32',
            'Security of processing & audit trail',
            'Art. 32',
            'Passende tekniske og organisatoriske sikkerhedsforanstaltninger, herunder sporbarhed for alle ændringer.',
            'data protection',
            self::check(
                "SELECT COUNT(*) n FROM audit_log",
                [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n hændelser logget i audit-loggen"];
                    return ['outstanding', 'Ingen audit-log fundet', 'Audit-logging bør være aktiveret. Tjek at Logger::log() kaldes ved alle kritiske operationer.'];
                }
            )
        );

        // Art. 33 – Breach notification
        $items[] = self::item(
            'gdpr_art33',
            'Breach notification procedures',
            'Art. 33–34',
            'Datakrænkelser skal kunne registreres, vurderes og rapporteres til tilsynsmyndighed inden 72 timer.',
            'data protection',
            self::check(
                "SELECT COUNT(*) n FROM tickets WHERE type = 'incident'",
                $cid ? null : null, // tickets are customer-scoped via Auth but query is raw; pass null and rely on controller gate
                function ($n) use ($cid) {
                    $cnt = $cid
                        ? (int)self::scalar("SELECT COUNT(*) FROM tickets WHERE type='incident' AND customer_id = ? ", [$cid])
                        : $n;
                    if ($cnt > 0) return ['partial', "$cnt incident-ticket(s) – systemet kan spore hændelser", 'Opret en decideret beredskabsplan for databrud (hvem vurderer, hvem rapporterer til Datatilsynet, hvornår). Dokumentér i vidensbasen.'];
                    return ['outstanding', 'Ingen incident-tickets fundet', 'Systemet kan registrere hændelser via incident-tickets. Opret en beredskabsplan for databrud og test processen.'];
                }
            )
        );

        // Art. 35 – DPIA
        $items[] = self::item(
            'gdpr_art35',
            'Data Protection Impact Assessment',
            'Art. 35',
            'DPIA skal udføres for behandlingsaktiviteter med høj risiko for brugernes rettigheder.',
            'data protection',
            self::check(
                "SELECT COUNT(*) n FROM knowledge_articles WHERE (title LIKE '%DPIA%' OR title LIKE '%impact assessment%' OR body LIKE '%DPIA%') AND status = 'published'",
                [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n DPIA-relateret artikel(er) i vidensbasen"];
                    return ['outstanding', 'Ingen DPIA dokumentation fundet', 'Udfør DPIA for behandlingsaktiviteter med høj risiko. Dokumentér resultatet i vidensbasen.'];
                }
            )
        );

        // Art. 28 – Processor agreements
        $items[] = self::item(
            'gdpr_art28',
            'Processor agreements',
            'Art. 28',
            'Databehandleraftaler skal være på plads med alle eksterne leverandører der behandler persondata.',
            'data protection',
            self::check(
                "SELECT COUNT(*) n FROM customers",
                [],
                function ($n) use ($cid) {
                    // External processors: hosting (Gigahost), email, potentially Azure/Intune
                    return ['partial', 'Systemet bruger Gigahost (hosting/email) og evt. Microsoft Azure/Intune', 'Dokumentér databehandleraftaler med Gigahost og Microsoft. Tjek at aftalerne opfylder Art. 28 krav.'];
                }
            )
        );

        // Art. 29 – Training / awareness
        $items[] = self::item(
            'gdpr_art29',
            'Staff training & awareness',
            'Art. 29 / 39(1)(b)',
            'Personale der behandler persondata skal have passende oplæring i databeskyttelse.',
            'accountability',
            self::check(
                "SELECT COUNT(*) n FROM knowledge_articles WHERE (title LIKE '%træning%' OR title LIKE '%training%' OR title LIKE '%awareness%' OR title LIKE '%sikkerhed%' OR title LIKE '%security%') AND status = 'published'",
                [],
                function ($n) {
                    if ($n > 0) return ['partial', "$n sikkerhedsrelateret artikel(er) i vidensbasen", 'Opret et decideret træningsforløb med dokumentation af deltagelse. Videnbasen er et godt sted at lægge materiale.'];
                    return ['outstanding', 'Ingen træningsmaterialer fundet', 'Opret sikkerhedstræning i vidensbasen og dokumentér at medarbejdere har gennemført den.'];
                }
            )
        );

        // Art. 37 – DPO
        $items[] = self::item(
            'gdpr_art37',
            'Data Protection Officer',
            'Art. 37–39',
            'DPO skal udnævnes hvis behandlingen kræver det (offentlige myndigheder, storstilet overvågning mv.).',
            'accountability',
            ['status' => 'not_assessed', 'evidence' => 'Kan ikke vurderes automatisk', 'hint' => 'Vurdér om DPO-udnævnelse er påkrævet efter Art. 37. Hvis ja: udnævn, dokumentér kontaktdata og læg dem på hjemmesiden.']
        );

        return self::framework('gdpr', 'GDPR', 'Forordning (EU) 2016/679 – Generel Forordning om Databeskyttelse', $items);
    }

    // ─────────────────────── ISO 27001:2022 ───────────────────────

    private static function iso27001(?int $cid): array
    {
        $items = [];

        // A.5.1 – Policies
        $items[] = self::item(
            'iso_a51',
            'Information security policies',
            'A.5.1',
            'Ledelsesforpligtet politik for informationssikkerhed skal være defineret, godkendt og kommunikeret.',
            'organizational',
            self::check(
                "SELECT COUNT(*) n FROM knowledge_articles WHERE (title LIKE '%politik%' OR title LIKE '%policy%' OR title LIKE '%sikkerhedspolitik%') AND status = 'published'",
                [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n politikrelateret artikel(er) fundet"];
                    return ['outstanding', 'Ingen sikkerhedspolitik dokumenteret', 'Opret en overordnet informationssikkerhedspolitik i vidensbasen. Den skal godkendes af ledelsen og være tilgængelig for alle medarbejdere.'];
                }
            )
        );

        // A.5.9 – Asset inventory
        $items[] = self::item(
            'iso_a59',
            'Inventory of information assets',
            'A.5.9',
            'Alle informationssystemer og aktiver skal identificeres, registreres og have en ejer.',
            'organizational',
            self::check(
                "SELECT COUNT(*) n FROM ci_items" . ($cid ? ' WHERE customer_id = ?' : ' WHERE is_active = 1'),
                $cid ? [$cid] : [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n CI(er) registreret i CMDB"];
                    return ['outstanding', 'CMDB er tom', 'Registrér alle informationssystemer, servere, applikationer og device i CMDB-modulet med ejer og placering.'];
                }
            )
        );

        // A.5.15 – Access control
        $items[] = self::item(
            'iso_a515',
            'Access control',
            'A.5.15',
            'Adgangsstyring skal baseres på forretningsbehov, mindste privilegium og rollebaseret adgang.',
            'organizational',
            self::check(
                "SELECT COUNT(*) n FROM roles",
                [],
                function ($n) {
                    $users = (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL");
                    if ($n > 0) return ['covered', "$n roller defineret, $users aktive brugere med rollebaseret adgang"];
                    return ['outstanding', 'Ingen roller defineret', 'Systemet har RBAC. Sikr at alle brugere har den korrekte rolle (admin/manager/agent/user).'];
                }
            )
        );

        // A.5.16 – Identity management
        $items[] = self::item(
            'iso_a516',
            'Identity management',
            'A.5.16',
            'Brugeridentiteter skal administreres gennem hele livscyklussen (oprettelse, ændring, deaktivering).',
            'organizational',
            self::check(
                "SELECT COUNT(*) n FROM users WHERE deleted_at IS NOT NULL",
                [],
                function ($n) use ($cid) {
                    $active = $cid
                        ? (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL AND customer_id = ?", [$cid])
                        : (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL");
                    $total = $cid
                        ? (int)self::scalar("SELECT COUNT(*) FROM users WHERE customer_id = ?", [$cid])
                        : (int)self::scalar("SELECT COUNT(*) FROM users");
                    return ['partial', "$active aktive / $total brugere i alt", 'Systemet understøtter soft-delete. Vurdér om SSO/Azure AD-integration skal aktiveres for central identitetsstyring.'];
                }
            )
        );

        // A.5.18 – Access rights review
        $items[] = self::item(
            'iso_a518',
            'Access rights review',
            'A.5.18',
            'Adgangsrettigheder skal gennemgås regelmæssigt og dove konti deaktiveres.',
            'organizational',
            self::check(
                "SELECT COUNT(*) n FROM users WHERE is_active = 1 AND deleted_at IS NULL AND last_login_at < DATE_SUB(NOW(), INTERVAL 90 DAY)",
                [],
                function ($n) use ($cid) {
                    $cnt = $cid
                        ? (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active=1 AND deleted_at IS NULL AND customer_id=? AND last_login_at < DATE_SUB(NOW(), INTERVAL 90 DAY)", [$cid])
                        : $n;
                    $active = $cid
                        ? (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL AND customer_id = ?", [$cid])
                        : (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL");
                    if ($active > 0 && $cnt / $active <= 0.2) return ['covered', "$cnt dove ud af $active aktive brugere (<= 20%)"];
                    return ['outstanding', "$cnt dove ud af $active aktive brugere (> 20%)", 'Gennemgå brugere med "Brugeradministration" og deaktiver konti der ikke længere er i brug. Indfør rutine for kvartalsvis adgangsgennemgang.'];
                }
            )
        );

        // A.8.1 – Endpoint devices (Intune)
        $items[] = self::item(
            'iso_a81',
            'Endpoint devices',
            'A.8.1',
            'Enheder der bruges til at få adgang til information skal registreres og beskyttes.',
            'technological',
            self::check(
                "SELECT COUNT(*) n FROM intune_devices",
                [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n enhed(er) registreret via Intune"];
                    return ['outstanding', 'Ingen enheder registreret (Intune-synkronisering blokeret)', 'Intune-synkronisering kræver Microsoft Graph API-tilladelse (DeviceManagementManagedDevices.Read.All). Bevilg admin consent i Azure Portal for at aktivere enhedsregistrering.'];
                }
            )
        );

        // A.8.9 – Configuration management
        $items[] = self::item(
            'iso_a89',
            'Configuration management',
            'A.8.9',
            'Konfigurationer skal styres, dokumenteres og gennemgås for uautoriserede ændringer.',
            'technological',
            self::check(
                "SELECT COUNT(*) n FROM ci_items WHERE owner_id IS NOT NULL" . ($cid ? ' AND customer_id = ?' : ''),
                $cid ? [$cid] : [],
                function ($n) use ($cid) {
                    $total = $cid
                        ? (int)self::scalar("SELECT COUNT(*) FROM ci_items WHERE customer_id = ?", [$cid])
                        : (int)self::scalar("SELECT COUNT(*) FROM ci_items");
                    if ($total > 0 && $n / $total >= 0.8) return ['covered', "$n af $total CI'er har ejer tildelt"];
                    return ['partial', "$n af $total CI'er har ejer tildelt", 'Tildel ejer og placering til alle CI\'er i CMDB. Uden ejer kan configuration management ikke fungere effektivt.'];
                }
            )
        );

        // A.8.13 – Backup
        $items[] = self::item(
            'iso_a813',
            'Information backup',
            'A.8.13',
            'Backup af information skal udføres og testes regelmæssigt.',
            'technological',
            ['status' => 'not_assessed', 'evidence' => 'Kræver ekstern dokumentation', 'hint' => 'Dokumentér backup-procedure (hvad, hvor ofte, hvor lagres). Test gendannelse regelmæssigt og dokumentér testresultater. Gigahost tilbyder backup-løsninger.']
        );

        // A.8.15 – Logging
        $items[] = self::item(
            'iso_a815',
            'Logging',
            'A.8.15',
            'Sikkerhedshændelser og systemaktivitet skal logges og beskyttes mod manipulation.',
            'technological',
            self::check(
                "SELECT COUNT(*) n FROM audit_log",
                [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n hændelser logget"];
                    return ['outstanding', 'Ingen audit-log fundet', 'Audit-logging skal være aktiveret. Tjek at alle kritiske operationer (login, opdatering, sletning) logges.'];
                }
            )
        );

        // A.8.16 – Monitoring
        $items[] = self::item(
            'iso_a816',
            'Monitoring activities',
            'A.8.16',
            'Sikkerhedsovervågning skal udføres for at detektere anomalier og hændelser.',
            'technological',
            self::check(
                "SELECT COUNT(*) n FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n hændelser inden for de seneste 30 dage"];
                    return ['partial', 'Ingen hændelser de seneste 30 dage', 'Systemet logger, men der bør være en rutine for at gennemgå logge regelmæssigt. Brug Reports → Audit Log.'];
                }
            )
        );

        // A.8.32 – Change management
        $items[] = self::item(
            'iso_a832',
            'Change management',
            'A.8.32',
            'Ændringer skal styres med godkendelse, test og dokumentation.',
            'technological',
            self::check(
                "SELECT COUNT(*) n FROM tickets WHERE type = 'change'",
                $cid ? null : null,
                function ($n) use ($cid) {
                    $cnt = $cid
                        ? (int)self::scalar("SELECT COUNT(*) FROM tickets WHERE type='change' AND customer_id=? ", [$cid])
                        : $n;
                    if ($cnt > 0) return ['covered', "$cnt change-ticket(s) registreret"];
                    return ['outstanding', 'Ingen change-tickets fundet', 'Brug Changes-modulet til at registrere og godkende alle IT-ændringer. Systemet har approval-workflow (CAB) til dette.'];
                }
            )
        );

        // A.6.5 – Responsibilities after termination
        $items[] = self::item(
            'iso_a65',
            'Responsibilities after termination',
            'A.6.5',
            'Efter opsigelse/afslutning skal adgangsrettigheder fjernes og fortrolighedsforpligtelser genbekræftes.',
            'people',
            self::check(
                "SELECT COUNT(*) n FROM users WHERE deleted_at IS NOT NULL",
                [],
                function ($n) {
                    if ($n > 0) return ['partial', "$n brugere anonymiseret/slettet – systemet understøtter proces", 'Ved afskedigelse: deaktiver brugeren (ikke slet), gennemgå alle tildelte rettigheder, og dokumentér at adgang er fjernet. Systemet anonymiserer email ved sletning.'];
                    return ['partial', 'Systemet understøtter soft-delete og anonymisering', 'Ved afskedigelse: deaktiver brugeren, gennemgå rettigheder, og dokumentér processen.'];
                }
            )
        );

        return self::framework('iso27001', 'ISO 27001:2022', 'Internationale standard for ledelsessystemer for informationssikkerhed (ISMS)', $items);
    }

    // ─────────────────────── D-mærket ───────────────────────

    private static function dmartket(?int $cid): array
    {
        $items = [];

        // ISM
        $items[] = self::item(
            'dm_ism',
            'Ledelsessystem for informationssikkerhed (ISM)',
            'D-mærket',
            'Et dokumenteret ledelsessystem for informationssikkerhed med politikker, procedurer og ansvar.',
            'ledelse',
            self::check(
                "SELECT COUNT(*) n FROM knowledge_articles WHERE (title LIKE '%politik%' OR title LIKE '%policy%' OR title LIKE '%procedure%' OR title LIKE '%sikkerhed%') AND status = 'published'",
                [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n politik/procedure-dokument(er) i vidensbasen"];
                    return ['outstanding', 'Ingen ISM-dokumentation fundet', 'Etabler et ledelsessystem for informationssikkerhed: overordnet politik, sikkerhedsprocedurer og dokumenterede ansvarsfordelinger. Læg dokumenterne i vidensbasen.'];
                }
            )
        );

        // Risikovurdering
        $items[] = self::item(
            'dm_risk',
            'Risikovurdering',
            'D-mærket',
            'Formaliseret risikovurdering af informationssikkerheden med dokumenterede risici og handlinger.',
            'ledelse',
            self::check(
                "SELECT COUNT(*) n FROM knowledge_articles WHERE (title LIKE '%risiko%' OR title LIKE '%risk%') AND status = 'published'",
                [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n risikorelateret artikel(er) fundet"];
                    return ['outstanding', 'Ingen risikovurdering dokumenteret', 'Gennemfør en risikovurdering: identificér trusler, vurdér sandsynlighed og konsekvens, prioritér risici, og definer handlingsplaner. Dokumentér i vidensbasen.'];
                }
            )
        );

        // Incident response
        $items[] = self::item(
            'dm_incident',
            'Incident-håndtering',
            'D-mærket',
            'Proces for at registrere, håndtere og lære af sikkerhedshændelser.',
            'operationel',
            self::check(
                "SELECT COUNT(*) n FROM tickets WHERE type = 'incident'",
                $cid ? null : null,
                function ($n) use ($cid) {
                    $cnt = $cid
                        ? (int)self::scalar("SELECT COUNT(*) FROM tickets WHERE type='incident' AND customer_id=? ", [$cid])
                        : $n;
                    $comments = (int)self::scalar("SELECT COUNT(*) FROM ticket_comments WHERE is_internal = 0");
                    if ($cnt > 0) return ['partial', "$cnt incident(s) registreret, $comments kommentar(er)", 'Systemet kan spore incidents. Opret en formaliseret incident-procedure (eskaleringsniveauer, kommunikation, post-incident review).'];
                    return ['outstanding', 'Ingen incidents registreret', 'Brug Incidents-modulet til at registrere sikkerhedshændelser. Opret en procedure for eskalering og rapportering.'];
                }
            )
        );

        // Adgangsstyring
        $items[] = self::item(
            'dm_access',
            'Adgangsstyring',
            'D-mærket',
            'Adgang til information styres efter principperne om mindste privilegium og løbende gennemgang.',
            'operationel',
            self::check(
                "SELECT COUNT(*) n FROM roles",
                [],
                function ($n) use ($cid) {
                    $dormant = $cid
                        ? (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active=1 AND deleted_at IS NULL AND customer_id=? AND last_login_at < DATE_SUB(NOW(), INTERVAL 90 DAY)", [$cid])
                        : (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active=1 AND deleted_at IS NULL AND last_login_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                    $active = $cid
                        ? (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active=1 AND deleted_at IS NULL AND customer_id=?", [$cid])
                        : (int)self::scalar("SELECT COUNT(*) FROM users WHERE is_active=1 AND deleted_at IS NULL");
                    if ($dormant > 0) return ['partial', "$dormant dove ud af $active aktive brugere", 'Gennemgå og deaktiver dove konti. Indfør rutine for regelmæssig adgangsgennemgang.'];
                    return ['covered', "$active aktive brugere, ingen dove konti fundet"];
                }
            )
        );

        // Logging og overvågning
        $items[] = self::item(
            'dm_logging',
            'Logging og overvågning',
            'D-mærket',
            'Sikkerhedshændelser logges og overvåges for at kunne detektere og reagere på incidents.',
            'operationel',
            self::check(
                "SELECT COUNT(*) n FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [],
                function ($n) {
                    if ($n > 0) return ['covered', "$n hændelser inden for de seneste 30 dage"];
                    return ['partial', 'Ingen nylige hændelser i loggen', 'Systemet logger automatisk. Etabler en rutine for at gennemgå audit-log regelmæssigt (brug Reports → Audit Log).'];
                }
            )
        );

        // Backup og gendannelse
        $items[] = self::item(
            'dm_backup',
            'Backup og gendannelse',
            'D-mærket',
            'Backup af kritisk information udføres regelmæssigt og gendannelse testes.',
            'operationel',
            ['status' => 'not_assessed', 'evidence' => 'Kræver ekstern dokumentation', 'hint' => 'Dokumentér backup-strategi (hyppighed, opbevaring, kryptering). Test gendannelse mindst årligt og dokumentér resultatet. Afklar med hosting-udbyder (Gigahost) hvilket backup der er inkluderet.']
        );

        // Medarbejdertræning
        $items[] = self::item(
            'dm_training',
            'Medarbejdertræning',
            'D-mærket',
            'Alle medarbejdere modtager løbende oplæring i informationssikkerhed.',
            'ledelse',
            self::check(
                "SELECT COUNT(*) n FROM knowledge_articles WHERE (title LIKE '%træning%' OR title LIKE '%training%' OR title LIKE '%awareness%' OR title LIKE '%sikkerhed%') AND status = 'published'",
                [],
                function ($n) {
                    if ($n > 0) return ['partial', "$n sikkerhedsrelateret artikel(er)", 'Opret et struktureret træningsforløb og dokumentér deltagelse. Genopfrisk årligt.'];
                    return ['outstanding', 'Ingen træningsmaterialer fundet', 'Opret sikkerhedstræning i vidensbasen. Planlæg årlig genopfriskning og dokumentér deltagelse.'];
                }
            )
        );

        // Leverandørstyring
        $items[] = self::item(
            'dm_vendor',
            'Leverandørstyring',
            'D-mærket',
            'Sikkerhedskrav til leverandører skal defineres, aftales og følges op.',
            'ledelse',
            ['status' => 'not_assessed', 'evidence' => 'Kræver ekstern dokumentation', 'hint' => 'Dokumentér leverandører med adgang til systemer og data (Gigahost, Microsoft/Intune, eventuelle konsulenter). Indhent sikkerhedsgarantier og indfør regelmæssig opfølgning.']
        );

        return self::framework('dmartket', 'D-mærket', 'Den danske statens mærkningsordning for digital sikkerhed og modning', $items);
    }

    // ─────────────────────── Helpers ───────────────────────

    private static function item(string $id, string $title, string $reference, string $description, string $category, array $eval): array
    {
        // $eval is either the result of self::check() or a direct array
        if (isset($eval['status'])) {
            return [
                'id' => $id,
                'title' => $title,
                'reference' => $reference,
                'description' => $description,
                'category' => $category,
                'status' => $eval['status'],
                'evidence' => $eval['evidence'] ?? null,
                'hint' => $eval['hint'] ?? null,
            ];
        }
        // self::check returns the raw check result; evaluate it
        $n = self::scalar($eval['sql'], $eval['params'] ?? []);
        $result = ($eval['cb'])($n);
        $status = $result[0] ?? 'not_assessed';
        $evidence = $result[1] ?? null;
        $hint = $result[2] ?? null;
        return [
            'id' => $id,
            'title' => $title,
            'reference' => $reference,
            'description' => $description,
            'category' => $category,
            'status' => $status,
            'evidence' => $evidence,
            'hint' => $hint,
        ];
    }

    private static function check(string $sql, ?array $params, callable $cb): array
    {
        return ['sql' => $sql, 'params' => $params ?? [], 'cb' => $cb];
    }

    private static function scalar(string $sql, array $params = [])
    {
        $row = $params
            ? Database::fetch($sql, $params)
            : Database::fetch($sql);
        if (!$row) return 0;
        return array_values($row)[0];
    }

    private static function framework(string $id, string $name, string $reference, array $items): array
    {
        $stats = ['covered' => 0, 'partial' => 0, 'outstanding' => 0, 'not_assessed' => 0, 'total' => count($items)];
        foreach ($items as $item) {
            $stats[$item['status']]++;
        }
        $assessed = $stats['covered'] + $stats['partial'] + $stats['outstanding'];
        $score = $assessed > 0
            ? round(($stats['covered'] + $stats['partial'] * 0.5) / $assessed * 100)
            : 0;

        // Rollup status
        $rollup = 'not_assessed';
        if ($assessed > 0) {
            if ($stats['outstanding'] === 0 && $stats['partial'] === 0) $rollup = 'compliant';
            elseif ($stats['outstanding'] === 0) $rollup = 'partial';
            else $rollup = 'non_compliant';
        }

        return [
            'id' => $id,
            'name' => $name,
            'reference' => $reference,
            'items' => $items,
            'stats' => $stats,
            'score' => $score,
            'status' => $rollup,
        ];
    }
}
