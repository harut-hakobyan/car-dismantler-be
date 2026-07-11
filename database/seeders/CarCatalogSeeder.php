<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\CarMake;
use App\Models\CarModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CarCatalogSeeder extends Seeder
{
    private const CATALOG = [
        'Toyota' => ['2000GT', '4Runner', '86', 'Alphard', 'Aqua', 'Avalon', 'Avanza', 'Aygo', 'bZ3', 'bZ4X', 'C-HR', 'Camry', 'Celica', 'Corolla', 'Corolla Cross', 'Crown', 'FJ Cruiser', 'Fortuner', 'GR86', 'GR Corolla', 'GR Supra', 'Harrier', 'HiAce', 'Highlander', 'Hilux', 'Innova', 'IQ', 'Land Cruiser', 'Matrix', 'Mirai', 'MR2', 'Paseo', 'Picnic', 'Previa', 'Prius', 'ProAce', 'Ractis', 'Raize', 'RAV4', 'Sequoia', 'Sienna', 'Starlet', 'Tacoma', 'Tercel', 'Tundra', 'Urban Cruiser', 'Vellfire', 'Verso', 'Vios', 'Yaris', 'Yaris Cross'],
        'Lexus' => ['CT', 'ES', 'GS', 'GX', 'HS', 'IS', 'LBX', 'LC', 'LFA', 'LM', 'LS', 'LX', 'NX', 'RC', 'RC F', 'RX', 'RZ', 'SC', 'TX', 'UX'],
        'Honda' => ['Accord', 'Acty', 'Airwave', 'BR-V', 'City', 'Civic', 'CR-V', 'CR-X', 'CR-Z', 'Crosstour', 'e', 'Element', 'Fit', 'Freed', 'HR-V', 'Insight', 'Integra', 'Jazz', 'Legend', 'Mobilio', 'N-Box', 'Odyssey', 'Passport', 'Pilot', 'Prelude', 'Ridgeline', 'S2000', 'Shuttle', 'Stepwgn', 'Stream', 'ZR-V'],
        'Acura' => ['CL', 'ILX', 'Integra', 'Legend', 'MDX', 'NSX', 'RDX', 'RL', 'RLX', 'RSX', 'SLX', 'TL', 'TLX', 'TSX', 'ZDX'],
        'Nissan' => ['100NX', '200SX', '300ZX', '350Z', '370Z', '400Z', 'Altima', 'Ariya', 'Cube', 'Frontier', 'GT-R', 'Juke', 'Kicks', 'Leaf', 'Maxima', 'Micra', 'Murano', 'Navara', 'Note', 'NV200', 'Pathfinder', 'Patrol', 'Pulsar', 'Qashqai', 'Rogue', 'Sentra', 'Skyline', 'Terrano', 'Titan', 'Versa', 'X-Trail', 'Xterra'],
        'Infiniti' => ['EX35', 'FX35', 'FX50', 'G35', 'G37', 'Q30', 'Q50', 'Q60', 'Q70', 'QX30', 'QX50', 'QX55', 'QX60', 'QX70', 'QX80'],
        'Mazda' => ['121', '2', '3', '5', '6', '626', '929', 'B-Series', 'BT-50', 'CX-3', 'CX-30', 'CX-5', 'CX-50', 'CX-60', 'CX-70', 'CX-9', 'CX-90', 'Demio', 'Mazda2', 'Mazda3', 'Mazda6', 'Millenia', 'MPV', 'MX-30', 'MX-5 Miata', 'Premacy', 'RX-7', 'RX-8', 'Tribute'],
        'Subaru' => ['Ascent', 'Baja', 'BRZ', 'Crosstrek', 'Forester', 'Impreza', 'Legacy', 'Levorg', 'Outback', 'Solterra', 'Tribeca', 'WRX', 'XV'],
        'Suzuki' => ['Across', 'Alto', 'Baleno', 'Carry', 'Celerio', 'Ertiga', 'Grand Vitara', 'Ignis', 'Jimny', 'Kizashi', 'Liana', 'S-Cross', 'Splash', 'Swift', 'SX4', 'Vitara', 'Wagon R', 'XL7'],
        'Mitsubishi' => ['3000GT', 'ASX', 'Attrage', 'Colt', 'Delica', 'Eclipse', 'Eclipse Cross', 'Galant', 'L200', 'Lancer', 'Lancer Evolution', 'Mirage', 'Montero', 'Outlander', 'Outlander PHEV', 'Pajero', 'Pajero Sport', 'Space Star', 'Triton'],
        'Daihatsu' => ['Applause', 'Charade', 'Cuore', 'Move', 'Rocky', 'Sirion', 'Terios', 'YRV'],
        'Isuzu' => ['D-Max', 'MU-7', 'MU-X', 'Rodeo', 'Trooper', 'VehiCROSS'],

        'BMW' => ['1 Series', '2 Series', '3 Series', '4 Series', '5 Series', '6 Series', '7 Series', '8 Series', 'i3', 'i4', 'i5', 'i7', 'i8', 'IX', 'M2', 'M3', 'M4', 'M5', 'M8', 'X1', 'X2', 'X3', 'X4', 'X5', 'X6', 'X7', 'XM', 'Z3', 'Z4'],
        'Mercedes-Benz' => ['A-Class', 'AMG GT', 'B-Class', 'C-Class', 'CLA', 'CLC', 'CLK', 'CLS', 'E-Class', 'EQA', 'EQB', 'EQC', 'EQE', 'EQS', 'G-Class', 'GLA', 'GLB', 'GLC', 'GLE', 'GLS', 'ML', 'R-Class', 'S-Class', 'SL', 'SLC', 'SLK', 'Sprinter', 'V-Class'],
        'Audi' => ['A1', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'E-Tron', 'Q2', 'Q3', 'Q4 e-tron', 'Q5', 'Q7', 'Q8', 'R8', 'RS3', 'RS4', 'RS5', 'RS6', 'RS7', 'S3', 'S4', 'S5', 'S6', 'TT'],
        'Volkswagen' => ['Amarok', 'Arteon', 'Atlas', 'Beetle', 'Bora', 'Caddy', 'CC', 'Crafter', 'Fox', 'Golf', 'ID.3', 'ID.4', 'ID.5', 'ID.Buzz', 'Jetta', 'Passat', 'Polo', 'Scirocco', 'Sharan', 'Taigo', 'T-Cross', 'Tiguan', 'Touareg', 'Touran', 'T-Roc', 'Up', 'Vento'],
        'Porsche' => ['718 Boxster', '718 Cayman', '911', '918 Spyder', 'Boxster', 'Cayenne', 'Cayman', 'Macan', 'Panamera', 'Taycan'],
        'Opel' => ['Adam', 'Agila', 'Astra', 'Combo', 'Corsa', 'Crossland', 'Grandland', 'Insignia', 'Meriva', 'Mokka', 'Vectra', 'Vivaro', 'Zafira'],
        'Smart' => ['#1', '#3', 'Forfour', 'Fortwo'],
        'Maybach' => ['57', '62', 'GLS Maybach', 'S-Class Maybach'],

        'Ford' => ['Bronco', 'C-Max', 'EcoSport', 'Edge', 'Escape', 'Everest', 'Expedition', 'Explorer', 'F-150', 'Falcon', 'Fiesta', 'Focus', 'Fusion', 'Galaxy', 'Ka', 'Kuga', 'Maverick', 'Mondeo', 'Mustang', 'Puma', 'Ranger', 'S-Max', 'Tourneo', 'Transit'],
        'Chevrolet' => ['Astro', 'Aveo', 'Blazer', 'Bolt EV', 'Camaro', 'Captiva', 'Colorado', 'Corvette', 'Cruze', 'Equinox', 'Impala', 'Malibu', 'Orlando', 'Silverado', 'Sonic', 'Spark', 'Suburban', 'Tahoe', 'Trailblazer', 'Traverse', 'Trax'],
        'GMC' => ['Acadia', 'Canyon', 'Savana', 'Sierra', 'Terrain', 'Yukon'],
        'Cadillac' => ['ATS', 'CT4', 'CT5', 'CTS', 'DeVille', 'Escalade', 'Lyriq', 'SRX', 'XT4', 'XT5', 'XT6'],
        'Buick' => ['Encore', 'Enclave', 'Envista', 'LaCrosse', 'Regal', 'Verano'],
        'Chrysler' => ['200', '300', 'Pacifica', 'PT Cruiser', 'Sebring', 'Voyager'],
        'Dodge' => ['Avenger', 'Caliber', 'Challenger', 'Charger', 'Dakota', 'Durango', 'Journey', 'Nitro', 'RAM Van', 'Viper'],
        'Jeep' => ['Cherokee', 'Commander', 'Compass', 'Gladiator', 'Grand Cherokee', 'Liberty', 'Patriot', 'Renegade', 'Wagoneer', 'Wrangler'],
        'Ram' => ['1500', '2500', '3500', 'ProMaster', 'Rebel', 'TRX'],
        'Tesla' => ['Cybertruck', 'Model 3', 'Model S', 'Model X', 'Model Y', 'Roadster'],
        'Rivian' => ['R1S', 'R1T'],
        'Lucid' => ['Air', 'Gravity'],

        'Hyundai' => ['Accent', 'Creta', 'Elantra', 'Genesis Coupe', 'Getz', 'i10', 'i20', 'i30', 'Ioniq', 'Ioniq 5', 'Ioniq 6', 'Kona', 'Palisade', 'Santa Cruz', 'Santa Fe', 'Sonata', 'Staria', 'Tucson', 'Venue'],
        'Kia' => ['Avella', 'Besta', 'Borrego', 'Carens', 'Carnival', 'Carstar', 'Ceed', 'Cerato', 'Clarus', 'Concord', 'Credos', 'Elan', 'Elan II', 'Enterprise', 'EV1', 'EV2', 'EV3', 'EV4', 'EV5', 'EV6', 'EV9', 'Joice', 'K3', 'K4', 'K5', 'K7', 'K8', 'K9', 'Lotze', 'Magentis', 'Mentor', 'Mohave', 'Morning', 'Niro', 'Opirus', 'Optima', 'Pegas', 'Picanto', 'Potentia', 'Pride', 'Pregio', 'Ray', 'Retona', 'Rio', 'Roadster', 'Rondo', 'Sedona', 'Seltos', 'Sephia', 'Shuma', 'Soluto', 'Sonet', 'Sorento', 'Soul', 'Spectra', 'Sportage', 'Stinger', 'Stonic', 'Telluride', 'Venga', 'XCeed'],
        'Genesis' => ['G70', 'G80', 'G90', 'GV60', 'GV70', 'GV80'],

        'Ferrari' => ['296 GTB', '360 Modena', '430 Scuderia', '458 Italia', '488 GTB', '812 Superfast', 'California', 'Enzo', 'F8 Tributo', 'F40', 'F50', 'LaFerrari', 'Portofino', 'Roma', 'SF90 Stradale'],
        'Lamborghini' => ['Aventador', 'Countach', 'Diablo', 'Gallardo', 'Huracán', 'Murciélago', 'Revuelto', 'Urus'],
        'Maserati' => ['Ghibli', 'GranCabrio', 'GranTurismo', 'Grecale', 'Levante', 'MC20', 'Quattroporte'],
        'Alfa Romeo' => ['147', '156', '159', '4C', 'Brera', 'Giulia', 'Giulietta', 'GT', 'MiTo', 'Spider', 'Stelvio'],
        'Fiat' => ['124 Spider', '500', '500L', '500X', 'Bravo', 'Doblo', 'Ducato', 'Fiorino', 'Grande Punto', 'Linea', 'Panda', 'Punto', 'Tipo'],
        'Lancia' => ['Delta', 'Lybra', 'Thema', 'Ypsilon'],
        'Pagani' => ['Huayra', 'Utopia', 'Zonda'],

        'Rolls-Royce' => ['Cullinan', 'Dawn', 'Ghost', 'Phantom', 'Spectre', 'Wraith'],
        'Bentley' => ['Bentayga', 'Continental GT', 'Flying Spur', 'Mulsanne'],
        'Aston Martin' => ['DB11', 'DB12', 'DB9', 'DBS', 'DBX', 'Rapide', 'V8 Vantage', 'V12 Vantage', 'Vanquish', 'Vantage'],
        'Jaguar' => ['E-Pace', 'F-Pace', 'F-Type', 'I-Pace', 'XE', 'XF', 'XJ', 'XK'],
        'Land Rover' => ['Defender', 'Discovery', 'Discovery Sport', 'Freelander', 'Range Rover', 'Range Rover Evoque', 'Range Rover Sport', 'Range Rover Velar'],
        'Mini' => ['Clubman', 'Cooper', 'Countryman', 'Paceman'],
        'McLaren' => ['570S', '600LT', '650S', '720S', '750S', '765LT', 'Artura', 'GT', 'P1', 'Senna'],
        'Lotus' => ['Elise', 'Emira', 'Esprit', 'Evora', 'Exige'],

        'Peugeot' => ['106', '107', '206', '207', '208', '3008', '301', '307', '308', '407', '408', '5008', '508', 'Boxer', 'Partner', 'RCZ'],
        'Citroën' => ['Berlingo', 'C1', 'C2', 'C3', 'C4', 'C5', 'C6', 'C8', 'DS3', 'DS4', 'DS5'],
        'Renault' => ['Arkana', 'Captur', 'Clio', 'Duster', 'Espace', 'Kadjar', 'Kangoo', 'Koleos', 'Laguna', 'Logan', 'Master', 'Megane', 'Scenic', 'Symbol', 'Talisman', 'Trafic', 'Twingo', 'Zoe'],
        'Alpine' => ['A110'],
        'DS Automobiles' => ['DS3', 'DS4', 'DS7', 'DS9'],

        'Volvo' => ['C30', 'C40', 'S40', 'S60', 'S80', 'S90', 'V40', 'V60', 'V90', 'XC40', 'XC60', 'XC70', 'XC90'],
        'Polestar' => ['Polestar 1', 'Polestar 2', 'Polestar 3', 'Polestar 4'],
        'Saab' => ['9-3', '9-5', '900', '9000'],
        'Koenigsegg' => ['Agera', 'CCX', 'Gemera', 'Jesko', 'Regera'],

        'Škoda' => ['Fabia', 'Kamiq', 'Karoq', 'Kodiaq', 'Octavia', 'Rapid', 'Scala', 'Superb'],
        'SEAT' => ['Arona', 'Ateca', 'Ibiza', 'Leon', 'Tarraco', 'Toledo'],
        'Cupra' => ['Ateca', 'Born', 'Formentor', 'Leon', 'Tavascan'],

        'BYD' => ['Atto 3', 'Dolphin', 'Han', 'Seal', 'Sealion 7', 'Song', 'Tang'],
        'Geely' => ['Atlas', 'Coolray', 'Emgrand', 'Monjaro', 'Okavango', 'Tugella'],
        'Chery' => ['Arrizo 5', 'Arrizo 8', 'Tiggo 2', 'Tiggo 4', 'Tiggo 7', 'Tiggo 8'],
        'Great Wall' => ['Poer', 'Wingle'],
        'Haval' => ['H2', 'H6', 'H9', 'Jolion'],
        'Tank' => ['300', '500', '700'],
        'NIO' => ['EC6', 'EC7', 'EL6', 'EL7', 'ES6', 'ES8', 'ET5', 'ET7'],
        'XPeng' => ['G6', 'G9', 'P5', 'P7'],
        'Li Auto' => ['L6', 'L7', 'L8', 'L9'],
        'Zeekr' => ['001', '007', '009', 'X'],
        'MG' => ['HS', 'MG3', 'MG4', 'MG5', 'MG6', 'ZS'],

        'Tata' => ['Altroz', 'Harrier', 'Nexon', 'Punch', 'Safari', 'Tiago', 'Tigor'],
        'Mahindra' => ['Bolero', 'Scorpio', 'Thar', 'XUV300', 'XUV700'],
        'Maruti Suzuki' => ['Alto', 'Baleno', 'Brezza', 'Celerio', 'Dzire', 'Ertiga', 'Swift', 'Wagon R'],

        'Lada' => ['Granta', 'Niva', 'Vesta'],
        'UAZ' => ['Hunter', 'Patriot'],
        'GAZ' => ['Gazelle', 'Sobol'],
        'Moskvich' => ['3', '6'],

        'Aurus' => ['Komendant', 'Senat'],

        'Dacia' => ['Duster', 'Jogger', 'Logan', 'Sandero', 'Spring'],

        'TOGG' => ['T10F', 'T10X'],

        'VinFast' => ['VF 3', 'VF 5', 'VF 6', 'VF 7', 'VF 8', 'VF 9'],

        'Proton' => ['Persona', 'Saga', 'X50', 'X70'],
        'Perodua' => ['Axia', 'Bezza', 'Myvi'],

        'Rimac' => ['Nevera'],

        'Spyker' => ['C8'],
        'Donkervoort' => ['D8', 'F22'],

        'BelGee' => ['X50', 'X70'],

        'Holden' => ['Colorado', 'Commodore', 'Cruze', 'Captiva', 'Trax'],
        'HSV' => ['Clubsport', 'GTS', 'Maloo'],
    ];

    public function run(): void
    {
        CarModel::query()->delete();
        CarMake::query()->delete();

        foreach (self::CATALOG as $makeName => $models) {
            $make = CarMake::query()->create([
                'name' => $makeName,
                'slug' => Str::slug($makeName),
                'region' => null,
            ]);

            foreach ($models as $modelName) {
                CarModel::query()->create([
                    'car_make_id' => $make->id,
                    'name' => $modelName,
                    'slug' => Str::slug($modelName),
                ]);
            }
        }

        foreach (Car::query()->get() as $car) {
            $makeId = CarMake::query()->where('name', $car->make)->value('id');
            $modelId = $makeId
                ? CarModel::query()
                    ->where('car_make_id', $makeId)
                    ->where('name', $car->model)
                    ->value('id')
                : null;

            if ($makeId && $modelId) {
                $car->update([
                    'car_make_id' => $makeId,
                    'car_model_id' => $modelId,
                ]);
            }
        }
    }
}
