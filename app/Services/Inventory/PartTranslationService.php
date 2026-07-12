<?php

namespace App\Services\Inventory;

use Illuminate\Support\Str;

class PartTranslationService
{
    public function translate(string $name): array
    {
        $normalized = $this->normalize($name);

        return [
            'ru' => $this->translateText($normalized, $this->rusExact(), $this->rusWords()),
            'hy' => $this->translateText($normalized, $this->armExact(), $this->armWords()),
        ];
    }

    private function translateText(string $value, array $exactMap, array $wordMap): string
    {
        $lower = Str::lower($value);
        if (isset($exactMap[$lower])) {
            return $exactMap[$lower];
        }

        $segments = preg_split('/(\s+|\/|,|;|:|-|\(|\)|\+|&)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($segments === false) {
            return $value;
        }

        $translated = [];
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (preg_match('/^\s+$|^[\/,;:\-\(\)\+&]$/u', $segment)) {
                $translated[] = $segment;
                continue;
            }

            $translated[] = $this->translatePhrase($segment, $wordMap, $exactMap);
        }

        $result = trim(preg_replace('/\s+/u', ' ', implode('', $translated)) ?? implode('', $translated));
        return $result !== '' ? $result : $value;
    }

    private function translatePhrase(string $phrase, array $wordMap, array $exactMap): string
    {
        $lower = Str::lower(trim($phrase));
        if (isset($exactMap[$lower])) {
            return $exactMap[$lower];
        }

        $parts = preg_split('/(\s+)/u', $phrase, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $phrase;
        }

        $translated = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\s+$/u', $part)) {
                $translated[] = $part;
                continue;
            }

            $translated[] = $this->translateToken($part, $wordMap);
        }

        return implode('', $translated);
    }

    private function translateToken(string $token, array $wordMap): string
    {
        $lower = Str::lower($token);
        if (isset($wordMap[$lower])) {
            $translated = $wordMap[$lower];

            return $this->matchCase($token, $translated);
        }

        return $token;
    }

    private function matchCase(string $source, string $translated): string
    {
        if (preg_match('/^[A-Z]/u', $source)) {
            return mb_convert_case($translated, MB_CASE_TITLE, 'UTF-8');
        }

        return $translated;
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    private function rusExact(): array
    {
        return [
            'engine' => 'Двигатель',
            'engine block' => 'Блок двигателя',
            'power plant' => 'Двигатель',
            'body' => 'Кузов',
            'front section' => 'Передняя часть',
            'rear section' => 'Задняя часть',
            'lighting' => 'Освещение',
            'lights' => 'Фонари',
            'transmission' => 'Трансмиссия',
            'steering' => 'Рулевое управление',
            'suspension' => 'Подвеска',
            'brakes' => 'Тормоза',
            'cooling' => 'Охлаждение',
            'fuel system' => 'Топливная система',
            'exhaust' => 'Выхлопная система',
            'electrical system' => 'Электрическая система',
            'interior' => 'Салон',
            'glass' => 'Стекло',
            'doors' => 'Двери',
            'mirrors' => 'Зеркала',
            'wheels' => 'Колёса',
            'air conditioning' => 'Кондиционер',
            'safety' => 'Безопасность',
            'trim' => 'Отделка',
        ];
    }

    private function armExact(): array
    {
        return [
            'engine' => 'Շարժիչ',
            'engine block' => 'Շարժիչի բլոկ',
            'power plant' => 'Շարժիչ',
            'body' => 'Թափք',
            'front section' => 'Առջևի հատված',
            'rear section' => 'Հետևի հատված',
            'lighting' => 'Լուսավորություն',
            'lights' => 'Լուսարձակներ',
            'transmission' => 'Փոխանցումատուփ',
            'steering' => 'Ղեկավարում',
            'suspension' => 'Կասեցում',
            'brakes' => 'Արգելակներ',
            'cooling' => 'Սառեցում',
            'fuel system' => 'Վառելիքի համակարգ',
            'exhaust' => 'Արտանետման համակարգ',
            'electrical system' => 'Էլեկտրական համակարգ',
            'interior' => 'Սրահ',
            'glass' => 'Ապակիներ',
            'doors' => 'Դռներ',
            'mirrors' => 'Հայելիներ',
            'wheels' => 'Անիվներ',
            'air conditioning' => 'Օդորակում',
            'safety' => 'Անվտանգություն',
            'trim' => 'Զարդապատում',
        ];
    }

    private function rusWords(): array
    {
        return [
            'assembly' => 'Сборка',
            'module' => 'Модуль',
            'unit' => 'Блок',
            'cover' => 'Крышка',
            'support' => 'Кронштейн',
            'bracket' => 'Кронштейн',
            'housing' => 'Корпус',
            'sensor' => 'Датчик',
            'switch' => 'Переключатель',
            'control' => 'Управление',
            'wiring' => 'Проводка',
            'harness' => 'Жгут',
            'battery' => 'Аккумулятор',
            'starter' => 'Стартер',
            'alternator' => 'Генератор',
            'radiator' => 'Радиатор',
            'fan' => 'Вентилятор',
            'pump' => 'Насос',
            'pipe' => 'Труба',
            'hose' => 'Шланг',
            'filter' => 'Фильтр',
            'intake' => 'Впуск',
            'manifold' => 'Коллектор',
            'turbocharger' => 'Турбокомпрессор',
            'intercooler' => 'Интеркулер',
            'bumper' => 'Бампер',
            'grille' => 'Решётка',
            'hood' => 'Капот',
            'fender' => 'Крыло',
            'door' => 'Дверь',
            'window' => 'Окно',
            'mirror' => 'Зеркало',
            'seat' => 'Сиденье',
            'steering wheel' => 'Рулевое колесо',
            'wheel' => 'Колесо',
            'axle' => 'Ось',
            'shock absorber' => 'Амортизатор',
            'spring' => 'Пружина',
            'caliper' => 'Суппорт',
            'rotor' => 'Диск',
            'pad' => 'Колодка',
            'lamp' => 'Лампа',
            'headlamp' => 'Фара',
            'tail lamp' => 'Задний фонарь',
            'tail' => 'Задний',
            'front' => 'Передний',
            'rear' => 'Задний',
            'left' => 'Левый',
            'right' => 'Правый',
            'inner' => 'Внутренний',
            'outer' => 'Наружный',
            'panel' => 'Панель',
            'trim' => 'Отделка',
            'handle' => 'Ручка',
            'lock' => 'Замок',
            'seal' => 'Уплотнитель',
            'guide' => 'Направляющая',
            'motor' => 'Мотор',
            'actuator' => 'Привод',
            'cable' => 'Трос',
            'switchgear' => 'Блок переключателей',
            'relay' => 'Реле',
            'fuse' => 'Предохранитель',
        ];
    }

    private function armWords(): array
    {
        return [
            'assembly' => 'հավաքական',
            'module' => 'մոդուլ',
            'unit' => 'բլոկ',
            'cover' => 'կափարիչ',
            'support' => 'ամրակ',
            'bracket' => 'ամրակ',
            'housing' => 'պատյան',
            'sensor' => 'սենսոր',
            'switch' => 'անջատիչ',
            'control' => 'կառավարում',
            'wiring' => 'լարեր',
            'harness' => 'մալուխային փնջ',
            'battery' => 'ակումուլյատոր',
            'starter' => 'ստարտեր',
            'alternator' => 'գեներատոր',
            'radiator' => 'ռադիատոր',
            'fan' => 'օդափոխիչ',
            'pump' => 'պոմպ',
            'pipe' => 'խողովակ',
            'hose' => 'ճկախողովակ',
            'filter' => 'ֆիլտր',
            'intake' => 'մուտք',
            'manifold' => 'կոլեկտոր',
            'turbocharger' => 'տուրբին',
            'intercooler' => 'ինտերկուլեր',
            'bumper' => 'բամպեր',
            'grille' => 'վանդակաճաղ',
            'hood' => 'կապոտ',
            'fender' => 'թև',
            'door' => 'դուռ',
            'window' => 'պատուհան',
            'mirror' => 'հայելի',
            'seat' => 'նստատեղ',
            'steering wheel' => 'ղեկ',
            'wheel' => 'անիվ',
            'axle' => 'առանցք',
            'shock absorber' => 'ամորտիզատոր',
            'spring' => 'զսպանակ',
            'caliper' => 'սուպորտ',
            'rotor' => 'դիսկ',
            'pad' => 'կոլոդկա',
            'lamp' => 'լամպ',
            'headlamp' => 'ֆարա',
            'tail lamp' => 'հետևի լույս',
            'tail' => 'հետևի',
            'front' => 'առջևի',
            'rear' => 'հետևի',
            'left' => 'ձախ',
            'right' => 'աջ',
            'inner' => 'ներքին',
            'outer' => 'արտաքին',
            'panel' => 'պանել',
            'trim' => 'զարդարանք',
            'handle' => 'բռնակ',
            'lock' => 'փական',
            'seal' => 'կնիք',
            'guide' => 'ուղեցույց',
            'motor' => 'մոտոր',
            'actuator' => 'ակտիվատոր',
            'cable' => 'մալուխ',
            'switchgear' => 'անջատիչների բլոկ',
            'relay' => 'ռելե',
            'fuse' => 'ապահովիչ',
        ];
    }
}
