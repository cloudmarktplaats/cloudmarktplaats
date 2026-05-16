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
                [
                    'name' => $name,
                    'path' => $slug,
                    'is_active' => true,
                ],
            );
        }
    }
}
