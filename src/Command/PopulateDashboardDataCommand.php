<?php

namespace App\Command;

use App\Entity\Apartment;
use App\Entity\Tenant;
use App\Entity\Payment;
use App\Entity\Project;
use App\Entity\SalesReport;
use App\Entity\Lease;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:populate-dashboard-data',
    description: 'Populate dashboard with sample data',
)]
class PopulateDashboardDataCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Populating dashboard with sample data...');

        // Create sample apartments
        $apartments = [];
        for ($i = 1; $i <= 5; $i++) {
            $apartment = new Apartment();
            $apartment->setName("Apartment {$i}A");
            $apartment->setAddress("123 Main St, Unit {$i}A, City, State");
            $apartment->setBedrooms(rand(1, 3));
            $apartment->setBathrooms(rand(1, 2));
            $apartment->setRentAmount(rand(1200, 3000));
            $apartment->setStatus(['available', 'occupied', 'maintenance'][rand(0, 2)]);
            $apartment->setDescription("Beautiful apartment with modern amenities");
            
            $this->entityManager->persist($apartment);
            $apartments[] = $apartment;
        }

        // Create sample tenants
        $tenants = [];
        $names = [
            ['John', 'Doe'],
            ['Jane', 'Smith'],
            ['Mike', 'Johnson'],
            ['Sarah', 'Williams'],
            ['David', 'Brown']
        ];
        
        foreach ($names as $index => $name) {
            $tenant = new Tenant();
            $tenant->setFirstName($name[0]);
            $tenant->setLastName($name[1]);
            $tenant->setEmail(strtolower($name[0] . $name[1]) . '@example.com');
            $tenant->setPhone('555-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT));
            $tenant->setMoveInDate(new \DateTime('-' . rand(30, 365) . ' days'));
            $tenant->setStatus(['active', 'inactive', 'moved_out'][rand(0, 2)]);
            $tenant->setAddress('123 Main St, Apt ' . ($index + 1) . ', City, State');
            $tenant->setEmergencyContact('555-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT));
            $tenant->setDateOfBirth(new \DateTime('-' . rand(18, 65) . ' years'));
            
            $this->entityManager->persist($tenant);
            $tenants[] = $tenant;
        }

        // Create sample leases
        foreach ($tenants as $index => $tenant) {
            $lease = new Lease();
            $lease->setTenant($tenant);
            $lease->setApartment($apartments[$index % count($apartments)]);
            $lease->setStartDate(new \DateTime('-' . rand(30, 365) . ' days'));
            $lease->setEndDate(new \DateTime('+' . rand(180, 365) . ' days'));
            $lease->setMonthlyRent($apartments[$index % count($apartments)]->getRentAmount());
            $lease->setStatus(['active', 'inactive', 'expired'][rand(0, 2)]);
            $lease->setNotes('Standard lease agreement');
            
            $this->entityManager->persist($lease);
        }

        // Create sample payments
        foreach ($tenants as $tenant) {
            $currentLease = $tenant->getCurrentLease();
            if ($currentLease) {
                for ($i = 0; $i < rand(1, 3); $i++) {
                    $payment = new Payment();
                    $payment->setAmount($currentLease->getMonthlyRent());
                    $payment->setPaymentDate(new \DateTime('-' . rand(1, 30) . ' days'));
                    $payment->setDueDate(new \DateTime('+' . rand(1, 30) . ' days'));
                    $payment->setStatus(['paid', 'pending', 'overdue'][rand(0, 2)]);
                    $payment->setPaymentMethod(['cash', 'check', 'bank_transfer', 'credit_card'][rand(0, 3)]);
                    $payment->setNotes('Monthly rent payment');
                    $payment->setTenant($tenant);
                    $payment->setApartment($currentLease->getApartment());
                    
                    $this->entityManager->persist($payment);
                }
            }
        }

        // Create sample projects
        $project = new Project();
        $project->setName('Foreigner Tower 220');
        $project->setDescription('Luxury apartment complex with modern amenities');
        $project->setAddress('Apartment 12 No. st. north point, USA');
        $project->setPrice('1500');
        $project->setTotalProperties(20);
        $project->setTotalSqft(1000);
        $project->setTeamSize(20);
        $project->setStatus('active');
        
        $this->entityManager->persist($project);

        // Create sample sales reports
        $salesData = [
            ['Esard award', 'esardaward@gmail.com', 'sale', '100234', 'paid'],
            ['Micale vandar', 'micalevandar@gmail.com', 'rent', '210000', 'pending'],
            ['Michel varade', 'michelvarade@gmail.com', 'sale', '140211', 'paid'],
            ['Anio ana', 'anioana@gmail.com', 'rent', '320233', 'pending'],
            ['Esard anamika', 'esardanamika@gmail.com', 'sale', '42100', 'paid'],
            ['Anabil anam', 'anabilanam@gmail.com', 'rent', '132456', 'pending']
        ];

        foreach ($salesData as $data) {
            $salesReport = new SalesReport();
            $salesReport->setSalesBy($data[0]);
            $salesReport->setEmail($data[1]);
            $salesReport->setSalesType($data[2]);
            $salesReport->setPrice($data[3]);
            $salesReport->setStatus($data[4]);
            $salesReport->setApartment($apartments[rand(0, count($apartments) - 1)]);
            $salesReport->setTenant($tenants[rand(0, count($tenants) - 1)]);
            
            $this->entityManager->persist($salesReport);
        }

        $this->entityManager->flush();

        $output->writeln('Sample data populated successfully!');
        return Command::SUCCESS;
    }
}
