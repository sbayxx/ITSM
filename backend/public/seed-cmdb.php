<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';

echo "<h2>Seeding CMDB demo data...</h2>";

// Get ci_type IDs by slug
$typeMap = [];
$types = Database::fetchAll("SELECT id, slug FROM ci_types");
foreach ($types as $t) {
    $typeMap[$t['slug']] = $t['id'];
}

// Get agent/admin/manager IDs for audit log
$users = Database::fetchAll("SELECT id FROM users WHERE role IN ('admin','agent','manager')");
$userIds = array_column($users, 'id');

function pickUser(array $ids): int {
    return $ids[array_rand($ids)];
}

// Clear old demo data but keep core seed CIs
// We'll update the old ones and add new ones alongside

$now = date('Y-m-d H:i:s');

// ── Define all CI items ──
$ciItems = [
    // Servers
    [
        'name' => 'Web Server 01', 'type' => 'server', 'ci_type_id' => $typeMap['server'] ?? null,
        'fqdn' => 'web01.prod.example.com', 'ip_address' => '10.0.1.10', 'mac_address' => '00:1A:2B:3C:00:10',
        'os' => 'Ubuntu', 'os_version' => '22.04 LTS', 'cpu' => '8 vCPU', 'ram' => '32 GB', 'disk_space' => '500 GB SSD',
        'vendor' => 'Dell', 'model' => 'PowerEdge R750', 'serial_number' => 'SN-WEB-001',
        'asset_tag' => 'AST-2024-001', 'location' => 'DC1-RackA-U10', 'department' => 'IT Operations',
        'support_group' => 'Server Team', 'business_service' => 'E-Commerce Platform',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-03-15', 'warranty_end' => '2027-03-15',
        'owner_id' => 1, 'description' => 'Primary web server hosting the e-commerce platform',
        'notes' => 'Runs Nginx 1.24 + PHP 8.2. Part of load-balanced cluster with web02.',
    ],
    [
        'name' => 'Web Server 02', 'type' => 'server', 'ci_type_id' => $typeMap['server'] ?? null,
        'fqdn' => 'web02.prod.example.com', 'ip_address' => '10.0.1.11', 'mac_address' => '00:1A:2B:3C:00:11',
        'os' => 'Ubuntu', 'os_version' => '22.04 LTS', 'cpu' => '8 vCPU', 'ram' => '32 GB', 'disk_space' => '500 GB SSD',
        'vendor' => 'Dell', 'model' => 'PowerEdge R750', 'serial_number' => 'SN-WEB-002',
        'asset_tag' => 'AST-2024-002', 'location' => 'DC1-RackA-U12', 'department' => 'IT Operations',
        'support_group' => 'Server Team', 'business_service' => 'E-Commerce Platform',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-03-15', 'warranty_end' => '2027-03-15',
        'owner_id' => 1, 'description' => 'Secondary web server in load-balanced cluster',
    ],
    [
        'name' => 'Database Server 01', 'type' => 'server', 'ci_type_id' => $typeMap['database'] ?? null,
        'fqdn' => 'db01.prod.example.com', 'ip_address' => '10.0.2.10', 'mac_address' => '00:1A:2B:3C:00:20',
        'os' => 'Ubuntu', 'os_version' => '22.04 LTS', 'cpu' => '16 vCPU', 'ram' => '64 GB', 'disk_space' => '2 TB NVMe',
        'vendor' => 'Dell', 'model' => 'PowerEdge R850', 'serial_number' => 'SN-DB-001',
        'asset_tag' => 'AST-2024-003', 'location' => 'DC1-RackB-U05', 'department' => 'IT Operations',
        'support_group' => 'DBA Team', 'business_service' => 'E-Commerce Platform',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-01-20', 'warranty_end' => '2027-01-20',
        'owner_id' => 2, 'description' => 'Primary MySQL 8.4 database server with replication',
    ],
    [
        'name' => 'Database Server 02 (Replica)', 'type' => 'server', 'ci_type_id' => $typeMap['database'] ?? null,
        'fqdn' => 'db02.prod.example.com', 'ip_address' => '10.0.2.11', 'mac_address' => '00:1A:2B:3C:00:21',
        'os' => 'Ubuntu', 'os_version' => '22.04 LTS', 'cpu' => '16 vCPU', 'ram' => '64 GB', 'disk_space' => '2 TB NVMe',
        'vendor' => 'Dell', 'model' => 'PowerEdge R850', 'serial_number' => 'SN-DB-002',
        'asset_tag' => 'AST-2024-004', 'location' => 'DC1-RackB-U07', 'department' => 'IT Operations',
        'support_group' => 'DBA Team', 'business_service' => 'E-Commerce Platform',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-01-20', 'warranty_end' => '2027-01-20',
        'owner_id' => 2, 'description' => 'Read replica for MySQL 8.4 used for reporting and backups',
    ],
    [
        'name' => 'Mail Server 01', 'type' => 'server', 'ci_type_id' => $typeMap['server'] ?? null,
        'fqdn' => 'mail01.example.com', 'ip_address' => '10.0.3.10', 'mac_address' => '00:1A:2B:3C:00:30',
        'os' => 'Debian', 'os_version' => '12', 'cpu' => '4 vCPU', 'ram' => '16 GB', 'disk_space' => '1 TB HDD',
        'vendor' => 'HP', 'model' => 'ProLiant DL360', 'serial_number' => 'SN-MAIL-001',
        'asset_tag' => 'AST-2023-015', 'location' => 'DC1-RackA-U20', 'department' => 'IT Operations',
        'support_group' => 'Server Team', 'business_service' => 'Corporate Email',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2023-06-01', 'warranty_end' => '2026-06-01',
        'owner_id' => 1, 'description' => 'Postfix/Dovecot email server for corporate communications',
    ],
    [
        'name' => 'DNS Server 01', 'type' => 'server', 'ci_type_id' => $typeMap['server'] ?? null,
        'fqdn' => 'ns01.example.com', 'ip_address' => '10.0.0.10', 'mac_address' => '00:1A:2B:3C:00:05',
        'os' => 'Debian', 'os_version' => '12', 'cpu' => '2 vCPU', 'ram' => '4 GB', 'disk_space' => '100 GB SSD',
        'vendor' => 'HP', 'model' => 'ProLiant DL20', 'serial_number' => 'SN-DNS-001',
        'asset_tag' => 'AST-2023-010', 'location' => 'DC1-RackC-U02', 'department' => 'IT Operations',
        'support_group' => 'Network Team', 'business_service' => 'Infrastructure',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2023-01-10', 'warranty_end' => '2026-01-10',
        'owner_id' => 2, 'description' => 'Primary DNS server (Bind9) - authoritative for example.com',
    ],
    [
        'name' => 'Backup Server 01', 'type' => 'server', 'ci_type_id' => $typeMap['server'] ?? null,
        'fqdn' => 'backup01.example.com', 'ip_address' => '10.0.4.10', 'mac_address' => '00:1A:2B:3C:00:40',
        'os' => 'Ubuntu', 'os_version' => '22.04 LTS', 'cpu' => '8 vCPU', 'ram' => '32 GB', 'disk_space' => '20 TB HDD',
        'vendor' => 'Dell', 'model' => 'PowerEdge R750xs', 'serial_number' => 'SN-BACKUP-001',
        'asset_tag' => 'AST-2024-010', 'location' => 'DC1-RackB-U15', 'department' => 'IT Operations',
        'support_group' => 'Backup Team', 'business_service' => 'Infrastructure',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-05-01', 'warranty_end' => '2027-05-01',
        'owner_id' => 1, 'description' => 'Automated backup server running Bacula enterprise',
    ],
    // Network Devices
    [
        'name' => 'Core Switch 01', 'type' => 'network', 'ci_type_id' => $typeMap['network'] ?? null,
        'fqdn' => 'core-sw01.example.com', 'ip_address' => '10.0.254.1', 'mac_address' => '00:1A:2B:3C:FF:01',
        'os' => 'Cisco IOS-XE', 'os_version' => '17.9.1', 'cpu' => null, 'ram' => null, 'disk_space' => null,
        'vendor' => 'Cisco', 'model' => 'Catalyst 9500-48Y4C', 'serial_number' => 'SN-NET-001',
        'asset_tag' => 'AST-2023-020', 'location' => 'DC1-RackC-U01', 'department' => 'IT Operations',
        'support_group' => 'Network Team', 'business_service' => 'Infrastructure',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2023-03-01', 'warranty_end' => '2028-03-01',
        'owner_id' => 2, 'description' => 'Core network switch connecting all server racks',
    ],
    [
        'name' => 'Distribution Switch Floor 1', 'type' => 'network', 'ci_type_id' => $typeMap['network'] ?? null,
        'fqdn' => null, 'ip_address' => '10.0.254.10', 'mac_address' => '00:1A:2B:3C:FF:10',
        'os' => 'Cisco IOS', 'os_version' => '15.2(7)E', 'cpu' => null, 'ram' => null, 'disk_space' => null,
        'vendor' => 'Cisco', 'model' => 'Catalyst 3850-48T', 'serial_number' => 'SN-NET-005',
        'asset_tag' => 'AST-2023-025', 'location' => 'Floor1-WiringCloset', 'department' => 'Facilities',
        'support_group' => 'Network Team', 'business_service' => 'Infrastructure',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2023-03-01', 'warranty_end' => '2028-03-01',
        'owner_id' => 2, 'description' => 'Distribution switch for first floor office network',
    ],
    [
        'name' => 'Firewall 01', 'type' => 'network', 'ci_type_id' => $typeMap['security'] ?? null,
        'fqdn' => 'fw01.example.com', 'ip_address' => '10.0.254.254', 'mac_address' => '00:1A:2B:3C:FF:FF',
        'os' => 'FortiOS', 'os_version' => '7.4.2', 'cpu' => null, 'ram' => null, 'disk_space' => null,
        'vendor' => 'Fortinet', 'model' => 'FortiGate 600F', 'serial_number' => 'SN-FW-001',
        'asset_tag' => 'AST-2023-030', 'location' => 'DC1-RackC-U03', 'department' => 'IT Security',
        'support_group' => 'Security Team', 'business_service' => 'Infrastructure',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2023-06-15', 'warranty_end' => '2028-06-15',
        'owner_id' => 2, 'description' => 'Perimeter firewall with IPS/IDS and VPN termination',
    ],
    [
        'name' => 'Wireless Controller', 'type' => 'network', 'ci_type_id' => $typeMap['network'] ?? null,
        'fqdn' => 'wlc01.example.com', 'ip_address' => '10.0.254.20', 'mac_address' => '00:1A:2B:3C:FF:20',
        'os' => 'Cisco IOS-XE', 'os_version' => '17.9.1', 'cpu' => null, 'ram' => null, 'disk_space' => null,
        'vendor' => 'Cisco', 'model' => '9800-40', 'serial_number' => 'SN-NET-010',
        'asset_tag' => 'AST-2024-015', 'location' => 'DC1-RackC-U04', 'department' => 'IT Operations',
        'support_group' => 'Network Team', 'business_service' => 'Corporate WiFi',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-02-01', 'warranty_end' => '2029-02-01',
        'owner_id' => 1, 'description' => 'Wireless LAN controller for corporate WiFi (24 APs)',
    ],
    // Applications
    [
        'name' => 'ERP Application', 'type' => 'application', 'ci_type_id' => $typeMap['application'] ?? null,
        'fqdn' => 'erp.example.com', 'ip_address' => '10.0.10.10',
        'os' => 'Red Hat Enterprise Linux', 'os_version' => '9.3', 'cpu' => '32 vCPU', 'ram' => '128 GB', 'disk_space' => '1 TB SSD',
        'vendor' => 'SAP', 'model' => 'SAP S/4HANA 2023', 'serial_number' => null,
        'asset_tag' => 'AST-2022-001', 'location' => 'DC1-VMware-ClusterA', 'department' => 'Finance',
        'support_group' => 'Application Team', 'business_service' => 'Enterprise Resource Planning',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2022-11-01', 'warranty_end' => '2027-11-01',
        'owner_id' => 3, 'description' => 'SAP S/4HANA ERP system for finance, supply chain, and HR',
    ],
    [
        'name' => 'CRM Application', 'type' => 'application', 'ci_type_id' => $typeMap['application'] ?? null,
        'fqdn' => 'crm.example.com', 'ip_address' => '10.0.10.20',
        'os' => 'Red Hat Enterprise Linux', 'os_version' => '9.3', 'cpu' => '16 vCPU', 'ram' => '64 GB', 'disk_space' => '500 GB SSD',
        'vendor' => 'Salesforce', 'model' => 'Salesforce Revenue Cloud', 'serial_number' => null,
        'asset_tag' => 'AST-2023-050', 'location' => 'DC1-VMware-ClusterA', 'department' => 'Sales',
        'support_group' => 'Application Team', 'business_service' => 'Customer Relationship Management',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2023-04-01', 'warranty_end' => '2026-04-01',
        'owner_id' => 3, 'description' => 'Salesforce CRM instance for sales pipeline and customer data',
    ],
    [
        'name' => 'Backup System', 'type' => 'application', 'ci_type_id' => $typeMap['application'] ?? null,
        'fqdn' => 'backup-console.example.com', 'ip_address' => '10.0.4.20',
        'os' => 'Ubuntu', 'os_version' => '22.04 LTS', 'cpu' => '4 vCPU', 'ram' => '8 GB', 'disk_space' => '100 GB SSD',
        'vendor' => 'Bacula Systems', 'model' => 'Bacula Enterprise 16', 'serial_number' => 'LIC-BACKUP-2024',
        'asset_tag' => 'AST-2024-011', 'location' => 'DC1-RackB-U16', 'department' => 'IT Operations',
        'support_group' => 'Backup Team', 'business_service' => 'Infrastructure',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-05-01', 'warranty_end' => '2027-05-01',
        'owner_id' => 1, 'description' => 'Bacula Enterprise backup management console',
    ],
    [
        'name' => 'Monitoring System', 'type' => 'application', 'ci_type_id' => $typeMap['application'] ?? null,
        'fqdn' => 'monitor.example.com', 'ip_address' => '10.0.5.10',
        'os' => 'Ubuntu', 'os_version' => '24.04 LTS', 'cpu' => '8 vCPU', 'ram' => '16 GB', 'disk_space' => '500 GB SSD',
        'vendor' => 'Zabbix', 'model' => 'Zabbix 7.0 LTS', 'serial_number' => null,
        'asset_tag' => 'AST-2024-020', 'location' => 'DC1-VMware-ClusterB', 'department' => 'IT Operations',
        'support_group' => 'Server Team', 'business_service' => 'Infrastructure',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-06-01', 'warranty_end' => '2027-06-01',
        'owner_id' => 1, 'description' => 'Zabbix monitoring system for all servers and network devices',
    ],
    // Workstations & Peripherals
    [
        'name' => 'Management Workstation - IT Admin', 'type' => 'workstation', 'ci_type_id' => $typeMap['workstation'] ?? null,
        'fqdn' => 'ws-admin01.example.com', 'ip_address' => '10.0.100.10', 'mac_address' => 'AA:BB:CC:DD:00:01',
        'os' => 'Windows', 'os_version' => '11 Pro 23H2', 'cpu' => 'Intel Core i7-13700', 'ram' => '32 GB', 'disk_space' => '1 TB SSD',
        'vendor' => 'Dell', 'model' => 'Precision 3660', 'serial_number' => 'SN-WS-001',
        'asset_tag' => 'AST-2024-030', 'location' => 'HQ-Office-201', 'department' => 'IT Operations',
        'support_group' => 'IT Support', 'business_service' => 'Internal IT',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-04-01', 'warranty_end' => '2027-04-01',
        'owner_id' => 1, 'description' => 'IT administrator primary workstation with admin tools',
    ],
    [
        'name' => 'Network Printer Floor 3', 'type' => 'peripheral', 'ci_type_id' => $typeMap['peripheral'] ?? null,
        'fqdn' => 'printer-f3.example.com', 'ip_address' => '10.0.150.50', 'mac_address' => 'AA:BB:CC:DD:00:50',
        'os' => null, 'os_version' => null, 'cpu' => null, 'ram' => null, 'disk_space' => null,
        'vendor' => 'HP', 'model' => 'LaserJet Enterprise M507', 'serial_number' => 'SN-PRN-003',
        'asset_tag' => 'AST-2023-100', 'location' => 'Floor3-Room305', 'department' => 'Facilities',
        'support_group' => 'IT Support', 'business_service' => 'Internal IT',
        'environment' => 'production', 'status' => 'maintenance',
        'purchase_date' => '2023-08-01', 'warranty_end' => '2026-08-01',
        'owner_id' => null, 'description' => 'Network multifunction printer - currently in maintenance due to paper jam issue',
    ],
    // Virtual Infrastructure
    [
        'name' => 'VMware vCenter 01', 'type' => 'virtual', 'ci_type_id' => $typeMap['virtual'] ?? null,
        'fqdn' => 'vcenter01.example.com', 'ip_address' => '10.0.200.10',
        'os' => 'VMware Photon OS', 'os_version' => '4.0', 'cpu' => '4 vCPU', 'ram' => '16 GB', 'disk_space' => '300 GB',
        'vendor' => 'VMware', 'model' => 'vCenter Server 8.0 U3', 'serial_number' => 'LIC-VC-2024-001',
        'asset_tag' => 'AST-2024-050', 'location' => 'DC1-VMware-ClusterA', 'department' => 'IT Operations',
        'support_group' => 'Server Team', 'business_service' => 'Virtualization Platform',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-01-01', 'warranty_end' => '2027-01-01',
        'owner_id' => 1, 'description' => 'VMware vCenter managing 3 ESXi hosts with 50+ VMs',
    ],
    [
        'name' => 'ESXi Host 01', 'type' => 'virtual', 'ci_type_id' => $typeMap['server'] ?? null,
        'fqdn' => 'esxi01.example.com', 'ip_address' => '10.0.200.11', 'mac_address' => '00:1A:2B:3C:EE:01',
        'os' => 'VMware ESXi', 'os_version' => '8.0 U3', 'cpu' => '56 vCPU (2x Xeon Gold 6438M)', 'ram' => '512 GB', 'disk_space' => '10 TB SSD',
        'vendor' => 'Dell', 'model' => 'PowerEdge R960', 'serial_number' => 'SN-ESXI-001',
        'asset_tag' => 'AST-2024-051', 'location' => 'DC1-RackA-U01', 'department' => 'IT Operations',
        'support_group' => 'Server Team', 'business_service' => 'Virtualization Platform',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-01-15', 'warranty_end' => '2029-01-15',
        'owner_id' => 1, 'description' => 'Primary VMware ESXi host running production workload VMs',
    ],
    // Mobile
    [
        'name' => 'Company iPhone - CEO', 'type' => 'mobile', 'ci_type_id' => $typeMap['mobile'] ?? null,
        'fqdn' => null, 'ip_address' => null, 'mac_address' => 'AA:BB:CC:DD:EE:01',
        'os' => 'iOS', 'os_version' => '18.0', 'cpu' => null, 'ram' => null, 'disk_space' => '256 GB',
        'vendor' => 'Apple', 'model' => 'iPhone 16 Pro', 'serial_number' => 'SN-MOB-001',
        'asset_tag' => 'AST-2024-060', 'location' => 'HQ-Office-301', 'department' => 'Executive',
        'support_group' => 'IT Support', 'business_service' => 'Corporate Communications',
        'environment' => 'production', 'status' => 'active',
        'purchase_date' => '2024-09-20', 'warranty_end' => '2026-09-20',
        'owner_id' => 3, 'description' => 'CEO company iPhone with MDM management',
    ],
    // Staging environment
    [
        'name' => 'ERP Staging Environment', 'type' => 'application', 'ci_type_id' => $typeMap['application'] ?? null,
        'fqdn' => 'erp-staging.example.com', 'ip_address' => '10.0.11.10',
        'os' => 'Red Hat Enterprise Linux', 'os_version' => '9.4', 'cpu' => '8 vCPU', 'ram' => '32 GB', 'disk_space' => '200 GB SSD',
        'vendor' => 'SAP', 'model' => 'SAP S/4HANA 2023', 'serial_number' => null,
        'asset_tag' => 'AST-2024-070', 'location' => 'DC1-VMware-ClusterB', 'department' => 'Development',
        'support_group' => 'DevOps Team', 'business_service' => 'Enterprise Resource Planning',
        'environment' => 'staging', 'status' => 'active',
        'purchase_date' => '2024-06-01', 'warranty_end' => '2027-06-01',
        'owner_id' => 1, 'description' => 'Staging environment for ERP testing and UAT',
    ],
    // Decommissioned
    [
        'name' => 'Legacy File Server', 'type' => 'server', 'ci_type_id' => $typeMap['server'] ?? null,
        'fqdn' => 'filesvr-legacy.example.com', 'ip_address' => '10.0.99.10',
        'os' => 'Windows', 'os_version' => 'Server 2012 R2', 'cpu' => '4 vCPU', 'ram' => '8 GB', 'disk_space' => '2 TB HDD',
        'vendor' => 'HP', 'model' => 'ProLiant DL380 Gen8', 'serial_number' => 'SN-FILE-001',
        'asset_tag' => 'AST-2019-001', 'location' => 'DC1-RackD-U10', 'department' => 'IT Operations',
        'support_group' => 'Server Team', 'business_service' => 'Legacy Infrastructure',
        'environment' => 'production', 'status' => 'decommissioned',
        'purchase_date' => '2019-03-01', 'warranty_end' => '2024-03-01',
        'owner_id' => 1, 'description' => 'Legacy file server - decommissioned and migrated to SharePoint Online',
    ],
];

// ── Insert / Update CI Items ──
echo "<h3>Updating/creating CI items...</h3>";

$ciIdMap = []; // old_id => new_id for relationships

foreach ($ciItems as $item) {
    $name = $item['name'];

    // Check if CI with this name exists
    $existing = Database::fetch("SELECT id FROM ci_items WHERE name = ?", [$name]);

    if ($existing) {
        $id = $existing['id'];
        Database::update('ci_items', $item, 'id = ?', [$id]);
        echo "Updated: $name (ID: $id)<br>";
        $ciIdMap[$name] = $id;
    } else {
        $id = Database::insert('ci_items', $item);
        echo "Created: $name (ID: $id)<br>";
        $ciIdMap[$name] = $id;
    }
}

// ── Relationships ──
echo "<h3>Creating relationships...</h3>";
Database::query("DELETE FROM ci_relationships WHERE 1=1"); // Clean slate

$relationships = [
    // Web cluster
    ['Core Switch 01', 'Web Server 01', 'connects_to'],
    ['Core Switch 01', 'Web Server 02', 'connects_to'],
    ['Web Server 01', 'Database Server 01', 'depends_on'],
    ['Web Server 02', 'Database Server 01', 'depends_on'],
    ['Web Server 01', 'ERP Application', 'hosts'],
    ['Web Server 02', 'CRM Application', 'hosts'],
    // Database
    ['Database Server 01', 'Database Server 02 (Replica)', 'replicates_to'],
    ['Database Server 01', 'Backup Server 01', 'backed_up_by'],
    ['Database Server 02 (Replica)', 'Backup Server 01', 'backed_up_by'],
    // Network topology
    ['Core Switch 01', 'Distribution Switch Floor 1', 'connects_to'],
    ['Core Switch 01', 'Wireless Controller', 'connects_to'],
    ['Core Switch 01', 'Firewall 01', 'connects_to'],
    ['Firewall 01', 'DNS Server 01', 'protects'],
    ['Distribution Switch Floor 1', 'Network Printer Floor 3', 'connects_to'],
    // Applications
    ['ERP Application', 'Database Server 01', 'depends_on'],
    ['CRM Application', 'Database Server 01', 'depends_on'],
    ['ERP Application', 'ERP Staging Environment', 'parent_of'],
    ['Monitoring System', 'Web Server 01', 'monitors'],
    ['Monitoring System', 'Database Server 01', 'monitors'],
    ['Monitoring System', 'Core Switch 01', 'monitors'],
    ['Monitoring System', 'Firewall 01', 'monitors'],
    // Virtualization
    ['VMware vCenter 01', 'ESXi Host 01', 'manages'],
    ['ESXi Host 01', 'Web Server 01', 'hosts'],
    ['ESXi Host 01', 'Database Server 01', 'hosts'],
    ['ESXi Host 01', 'ERP Application', 'hosts'],
    ['ESXi Host 01', 'CRM Application', 'hosts'],
    ['ESXi Host 01', 'Monitoring System', 'hosts'],
    ['ESXi Host 01', 'ERP Staging Environment', 'hosts'],
    // Mail dependencies
    ['Mail Server 01', 'DNS Server 01', 'depends_on'],
    ['Mail Server 01', 'Firewall 01', 'depends_on'],
    // Mobile
    ['Wireless Controller', 'Company iPhone - CEO', 'provides_connectivity'],
    // Backup
    ['Backup Server 01', 'Backup System', 'runs_on'],
    // Legacy
    ['Legacy File Server', 'Core Switch 01', 'connected_to'],
];

$relCount = 0;
foreach ($relationships as [$parent, $child, $type]) {
    $pid = $ciIdMap[$parent] ?? null;
    $cid = $ciIdMap[$child] ?? null;
    if ($pid && $cid) {
        try {
            Database::insert('ci_relationships', [
                'parent_ci_id' => $pid,
                'child_ci_id' => $cid,
                'relationship_type' => $type,
            ]);
            $relCount++;
        } catch (Exception $e) {
            echo "  Relationship skipped ($parent → $child): {$e->getMessage()}<br>";
        }
    } else {
        echo "  Relationship skipped: missing CI ($parent → $child)<br>";
    }
}
echo "Created $relCount relationships.<br>";

// ── Tags ──
echo "<h3>Creating tags...</h3>";

$tagDefs = [
    ['Critical Infrastructure', '#ef4444'],
    ['PCI DSS Compliant', '#f59e0b'],
    ['High Availability Cluster', '#10b981'],
    ['End of Life - 2026', '#6b7280'],
    ['Managed by DevOps', '#6366f1'],
    ['Security Hardened', '#06b6d4'],
    ['Backup Daily', '#8b5cf6'],
    ['Compliance Audited', '#ec4899'],
];

Database::query("DELETE FROM ci_item_tags WHERE 1=1");
// Keep existing tags but we'll reuse or create
foreach ($tagDefs as [$name, $color]) {
    $existing = Database::fetch("SELECT id FROM ci_tags WHERE name = ?", [$name]);
    if (!$existing) {
        Database::insert('ci_tags', ['name' => $name, 'color' => $color]);
    }
}

$allTags = Database::fetchAll("SELECT id, name FROM ci_tags");
$tagByName = [];
foreach ($allTags as $t) {
    $tagByName[$t['name']] = $t['id'];
}

// Assign tags to CIs
$ciTags = [
    'Web Server 01' => ['Critical Infrastructure', 'High Availability Cluster', 'Security Hardened', 'Backup Daily'],
    'Web Server 02' => ['Critical Infrastructure', 'High Availability Cluster', 'Backup Daily'],
    'Database Server 01' => ['Critical Infrastructure', 'PCI DSS Compliant', 'High Availability Cluster', 'Backup Daily', 'Compliance Audited'],
    'Database Server 02 (Replica)' => ['Critical Infrastructure', 'Backup Daily'],
    'Firewall 01' => ['Critical Infrastructure', 'Security Hardened', 'Compliance Audited'],
    'ERP Application' => ['Critical Infrastructure', 'PCI DSS Compliant', 'Managed by DevOps', 'Compliance Audited'],
    'CRM Application' => ['Managed by DevOps', 'Backup Daily'],
    'Core Switch 01' => ['Critical Infrastructure'],
    'Backup Server 01' => ['Backup Daily', 'Security Hardened'],
    'Monitoring System' => ['Managed by DevOps'],
    'Legacy File Server' => ['End of Life - 2026'],
    'Company iPhone - CEO' => ['Security Hardened'],
];

$tagCount = 0;
foreach ($ciTags as $ciName => $tagNames) {
    $ciId = $ciIdMap[$ciName] ?? null;
    if (!$ciId) continue;
    foreach ($tagNames as $tn) {
        $tagId = $tagByName[$tn] ?? null;
        if ($tagId) {
            try {
                Database::insert('ci_item_tags', ['ci_item_id' => $ciId, 'tag_id' => $tagId]);
                $tagCount++;
            } catch (Exception $e) {
                // Duplicate skip
            }
        }
    }
}
echo "Assigned $tagCount tags to CI items.<br>";

// ── Audit Log ──
echo "<h3>Creating audit log entries...</h3>";
Database::query("DELETE FROM ci_audit_log WHERE 1=1");

$auditEntries = [
    ['Web Server 01', 'update', 'status', 'maintenance', 'Status changed from maintenance to active after firmware update'],
    ['Web Server 01', 'update', 'os_version', '20.04 LTS', 'OS upgraded from Ubuntu 20.04 to 22.04 LTS'],
    ['Database Server 01', 'create', null, null, 'CI item created through automated discovery'],
    ['Database Server 01', 'update', 'ram', '32 GB', 'RAM upgraded from 32GB to 64GB for performance improvement'],
    ['Firewall 01', 'update', 'os_version', '7.2.1', 'FortiOS upgraded from 7.2.1 to 7.4.2'],
    ['Firewall 01', 'update', 'vendor', 'Fortinet', 'Vendor information added during asset audit'],
    ['ERP Application', 'update', 'status', 'active', 'CI activated after successful UAT sign-off'],
    ['ERP Application', 'create', null, null, 'CI registered during SAP S/4HANA migration project'],
    ['Core Switch 01', 'update', 'location', 'DC1-RackC-U01', 'Physical location updated during datacenter cleanup'],
    ['Legacy File Server', 'update', 'status', 'decommissioned', 'Server decommissioned - data migrated to SharePoint Online'],
    ['Monitoring System', 'create', null, null, 'CI created as part of Zabbix deployment project'],
    ['Company iPhone - CEO', 'create', null, null, 'Mobile device enrolled in MDM and registered in CMDB'],
    ['Network Printer Floor 3', 'update', 'status', 'maintenance', 'Status changed to maintenance due to recurring paper jam issue - ticket INC-003 created'],
];

foreach ($auditEntries as [$ciName, $action, $field, $oldVal, $summary]) {
    $ciId = $ciIdMap[$ciName] ?? null;
    if (!$ciId) continue;
    Database::insert('ci_audit_log', [
        'ci_item_id' => $ciId,
        'user_id' => pickUser($userIds),
        'action' => $action,
        'field_name' => $field,
        'old_value' => $oldVal,
        'summary' => $summary,
        'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days')),
    ]);
}
echo "Created " . count($auditEntries) . " audit log entries.<br>";

// ── Summary ──
$totalCIs = Database::fetch("SELECT COUNT(*) as c FROM ci_items")['c'];
$totalRels = Database::fetch("SELECT COUNT(*) as c FROM ci_relationships")['c'];
$totalTags = Database::fetch("SELECT COUNT(*) as c FROM ci_item_tags")['c'];
$totalAudit = Database::fetch("SELECT COUNT(*) as c FROM ci_audit_log")['c'];

echo "<hr>";
echo "<h2>CMDB seeded successfully!</h2>";
echo "<ul>";
echo "<li><strong>$totalCIs</strong> configuration items</li>";
echo "<li><strong>$totalRels</strong> relationships</li>";
echo "<li><strong>$totalTags</strong> tag assignments</li>";
echo "<li><strong>$totalAudit</strong> audit log entries</li>";
echo "<li><strong>11</strong> CI type definitions</li>";
echo "</ul>";
