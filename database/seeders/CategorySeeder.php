<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tops = [
            'Server hardware' => 'servers',
            'Networking' => 'networking',
            'Storage' => 'storage',
            'Compute' => 'compute',
            'Kabels & connectoren' => 'kabels',
            'Power' => 'power',
            'Audio/Video pro' => 'av',
            'Meetapparatuur' => 'meet',
            '3D printers & CNC' => 'fabrication',
            'Software licenties' => 'licenses',
            'Boeken & documentatie' => 'books',
            'Overig' => 'misc',
        ];

        foreach ($tops as $name => $slug) {
            Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'path' => $slug, 'is_active' => true],
            );
        }

        // Subcategories. Keyed by parent slug → [display name => leaf slug].
        // Leaf slugs are globally unique and double as the ltree path segment
        // (alphanumeric only — no hyphens, which ltree labels forbid). The
        // ltree path is "{parent}.{leaf}", so browse's descendant filtering
        // ("all of compute.*") works automatically.
        $subs = [
            'compute' => [
                'Processoren (CPU)' => 'cpu',
                'Videokaarten (GPU)' => 'gpu',
                'Geheugen (RAM)' => 'ram',
                'Moederborden' => 'moederbord',
                'Koeling' => 'koeling',
                'Behuizingen' => 'behuizing',
                "Barebones & mini-PC's" => 'barebone',
                'Laptops & notebooks' => 'laptop',
                'Desktops & workstations' => 'desktop',
                'Thin clients' => 'thinclient',
                'Dev boards & SBC' => 'sbc',
                'Monitoren' => 'monitor',
                'KVM-switches' => 'kvm',
                'Toetsenbord, muis & randapparatuur' => 'randapparatuur',
            ],
            'servers' => [
                'Rackservers' => 'rackserver',
                'Towerservers' => 'towerserver',
                'Blade servers & chassis' => 'blade',
                'Serveronderdelen' => 'serveronderdeel',
                'Rails & rackmontage' => 'rails',
                'Serverkasten & racks' => 'rack',
            ],
            'storage' => [
                "SSD's" => 'ssd',
                'Harde schijven (HDD)' => 'hdd',
                'NVMe & M.2' => 'nvme',
                'NAS' => 'nas',
                'SAN & disk shelves' => 'san',
                'RAID/HBA-controllers' => 'controller',
                'Tape & backup' => 'tape',
            ],
            'networking' => [
                'Switches' => 'switch',
                'Routers' => 'router',
                'Firewalls & security appliances' => 'firewall',
                'Access points & wifi' => 'accesspoint',
                'Netwerkkaarten (NIC)' => 'nic',
                'Transceivers (SFP/QSFP)' => 'transceiver',
                'VoIP & telefonie' => 'voip',
            ],
            'power' => [
                'Voedingen (PSU)' => 'voeding',
                'UPS' => 'ups',
                'PDU & rackstroom' => 'pdu',
            ],
            'kabels' => [
                'Netwerkkabels' => 'netwerkkabel',
                'Stroomkabels' => 'stroomkabel',
                'Data-, SAS- & SATA-kabels' => 'datakabel',
                'Adapters & converters' => 'adapter',
            ],
            'av' => [
                'Beamers & projectoren' => 'beamer',
                'Schermen & signage' => 'signage',
                'Audio (mengpanelen, versterkers, speakers)' => 'audio',
                'Microfoons' => 'microfoon',
                "Camera's & capture" => 'camera',
                'Streaming & encoders' => 'streaming',
            ],
            'meet' => [
                'Oscilloscopen' => 'oscilloscoop',
                'Multimeters' => 'multimeter',
                'Labvoedingen' => 'labvoeding',
                'Logic analyzers' => 'logic',
                'Functiegeneratoren' => 'functiegenerator',
                'Soldeer- & reworkstations' => 'soldeer',
            ],
            'fabrication' => [
                'FDM-printers' => 'fdm',
                'Resin-printers (SLA/DLP)' => 'resin',
                'CNC-frezen & lasers' => 'cnc',
                'Filament & resin' => 'filament',
                'Printeronderdelen & upgrades' => 'printeronderdeel',
            ],
        ];

        foreach ($subs as $parentSlug => $children) {
            foreach ($children as $name => $leaf) {
                Category::firstOrCreate(
                    ['slug' => $leaf],
                    ['name' => $name, 'path' => $parentSlug.'.'.$leaf, 'is_active' => true],
                );
            }
        }
    }
}
