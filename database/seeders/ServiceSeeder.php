<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the repair and labour services a computer and mobile shop bills for.
     */
    public function run(): void
    {
        $deviceModel = $this->field('device_model', 'Device model', 'text', true, 'e.g. Galaxy A15, HP 15-fd0000');
        $imei = $this->field('imei', 'IMEI / Serial number', 'text', false, 'Dial *#06# to show the IMEI');
        $partQuality = $this->field('part_quality', 'Part quality', 'select', true, '', ['Original', 'OEM / High copy', 'Copy']);
        $faultDetails = $this->field('fault_details', 'Fault details', 'textarea', false, 'What the customer reported');

        $services = [
            ['Phone Screen Replacement', 3500, 'Labour to replace a cracked or faulty phone display. Bill the display separately.', [$deviceModel, $imei, $partQuality]],
            ['Phone Battery Replacement', 1500, 'Labour to replace a swollen or weak phone battery.', [$deviceModel, $imei, $partQuality, $this->field('battery_health', 'Battery health before (%)', 'number')]],
            ['Charging Port Repair', 2500, 'Clean or replace the charging port / sub board.', [$deviceModel, $imei]],
            ['Back Glass Replacement', 4000, 'Replace a cracked rear glass panel.', [$deviceModel, $partQuality]],
            ['Water Damage Treatment', 3500, 'Ultrasonic cleaning and board drying. No guarantee of recovery.', [$deviceModel, $imei, $faultDetails]],
            ['Phone Software Flashing', 2000, 'Reinstall firmware for boot loops, hangs and software faults.', [$deviceModel, $imei, $this->field('data_backup', 'Customer data backed up', 'checkbox')]],
            ['FRP / Pattern Lock Removal', 2500, 'Remove a forgotten screen lock or Google account lock with proof of ownership.', [$deviceModel, $imei, $this->field('ownership_proof', 'Proof of ownership checked', 'checkbox', true)]],
            ['Laptop / PC Diagnosis', 1000, 'Full hardware and software inspection. Waived if the repair is done here.', [$deviceModel, $faultDetails]],
            ['Laptop Screen Replacement', 3000, 'Labour to fit a new laptop panel. Bill the screen separately.', [$deviceModel, $this->field('screen_type', 'Screen type', 'select', true, '', ['30-pin FHD', '40-pin FHD', '30-pin HD', 'Touch'])]],
            ['Laptop Keyboard Replacement', 2500, 'Labour to replace a laptop keyboard.', [$deviceModel]],
            ['Laptop Hinge Repair', 3500, 'Repair broken hinges and hinge mounts.', [$deviceModel, $faultDetails]],
            ['Laptop Cleaning & Thermal Paste', 2500, 'Dust cleaning, fan service and new thermal paste for overheating.', [$deviceModel]],
            ['Motherboard Chip-Level Repair', 7500, 'Component-level board repair for no power, no display and short circuits.', [$deviceModel, $this->field('fault_details', 'Fault details', 'textarea', true, 'Symptoms and anything already tested')]],
            ['Windows Installation', 2500, 'Clean install of Windows with drivers and updates.', [
                $deviceModel,
                $this->field('os_version', 'Windows version', 'select', true, '', ['Windows 11 Pro', 'Windows 11 Home', 'Windows 10 Pro', 'Windows 10 Home']),
                $this->field('data_backup', 'Back up customer data first', 'checkbox'),
            ]],
            ['Software Installation', 1500, 'Install office, antivirus or other customer-licensed software.', [$this->field('software', 'Software installed', 'text', true, 'e.g. MS Office 2021, Adobe Reader')]],
            ['Virus & Malware Removal', 2000, 'Scan and remove viruses, adware and unwanted startup programs.', [$deviceModel]],
            ['Data Backup & Recovery', 5000, 'Copy or recover data from a working or failing drive.', [
                $this->field('source_device', 'Source device', 'select', true, '', ['HDD', 'SSD', 'Pen drive', 'Memory card', 'Phone']),
                $this->field('data_size', 'Approximate data size', 'text', false, 'e.g. 120GB'),
                $this->field('recovery_media', 'Customer brought recovery drive', 'checkbox'),
            ]],
            ['SSD / RAM Upgrade Installation', 1500, 'Fit a new SSD or RAM module and clone or set up the system.', [$deviceModel, $this->field('clone_os', 'Clone existing OS', 'checkbox')]],
            ['Desktop PC Assembly', 3500, 'Assemble a custom PC, cable manage and install drivers.', [$this->field('build_notes', 'Build notes', 'textarea', false, 'Parts list or special requests')]],
            ['Printer Service & Head Cleaning', 2000, 'Clean print heads, rollers and fix paper jams.', [$this->field('printer_model', 'Printer model', 'text', true, 'e.g. Epson L3250')]],
            ['Network / Router Setup', 2500, 'Configure routers, Wi-Fi and shared printers.', [
                $this->field('on_site', 'On-site visit', 'checkbox'),
                $this->field('site_address', 'Site address', 'text', false, 'Only for on-site visits'),
            ]],
        ];

        foreach ($services as [$name, $defaultPrice, $description, $customFields]) {
            Service::query()->firstOrCreate(['name' => $name], [
                'default_price' => $defaultPrice,
                'description' => $description,
                'custom_fields' => $customFields,
                'active' => true,
            ]);
        }
    }

    /**
     * Build a custom field in the shape the service form stores.
     *
     * @param  list<string>  $options
     * @return array{id: string, label: string, type: string, required: bool, placeholder: string, options: list<string>}
     */
    private function field(string $id, string $label, string $type, bool $required = false, string $placeholder = '', array $options = []): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => $type,
            'required' => $required,
            'placeholder' => $placeholder,
            'options' => $options,
        ];
    }
}
