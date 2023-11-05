<?php

$tarifBase = $_POST['tarifBase'] ?? 0.2276;
$aboBase = $_POST['aboBase'] ?? 12.44;
$horaireHC1 = $_POST['horaireHC1'] ?? '2200-0600';
$horaireHC2 = $_POST['horaireHC2'] ?? '';
$tarifHP = $_POST['tarifHP'] ?? 0.2460;
$tarifHC = $_POST['tarifHC'] ?? 0.1828;
$aboHCHP = $_POST['aboHCHP'] ?? 12.85;
$aboTempo = $_POST['aboTempo'] ?? 12.80;
$tarifTempoRedHP = $_POST['tarifTempoRedHP'] ?? 0.7324;
$tarifTempoRedHC = $_POST['tarifTempoRedHC'] ?? 0.1328;
$tarifTempoWhiteHP = $_POST['tarifTempoWhiteHP'] ?? 0.1654;
$tarifTempoWhiteHC = $_POST['tarifTempoWhiteHC'] ?? 0.1246;
$tarifTempoBlueHP = $_POST['tarifTempoBlueHP'] ?? 0.1369;
$tarifTempoBlueHC = $_POST['tarifTempoBlueHC'] ?? 0.1056;
$excludeDays = $_POST['excludeDays'] ?? '';

//$tempoHistoYear = 2022;
//while($tempoHistoYear <= date('Y')) {
////    echo 'https://particulier.edf.fr/services/rest/referentiel/historicTEMPOStore?dateBegin='.$tempoHistoYear.'&dateEnd='.($tempoHistoYear+1); exit;
//    $json = file_get_contents('https://particulier.edf.fr/services/rest/referentiel/historicTEMPOStore?dateBegin='.$tempoHistoYear.'&dateEnd='.($tempoHistoYear+1));
////    $json = json_decode(file_get_contents('https://particulier.edf.fr/services/rest/referentiel/historicTEMPOStore?dateBegin='.$tempoHistoYear.'&dateEnd='.($tempoHistoYear+1)), true);
//    var_dump($json); exit;
//    $tempoHistoYear++;
//}

function isHC($periodsHC, $currentHour) {
	foreach ($periodsHC as $periodHC) {
			if (
					($periodHC['start'] < $periodHC['end'] && $currentHour > $periodHC['start'] && $currentHour <= $periodHC['end']) // period in the same day
					 || ($periodHC['start'] > $periodHC['end'] && ( $currentHour > $periodHC['start'] || $currentHour <= $periodHC['end'] )) // period across 2 days
			) {
				  return true;
			}
	}	
	return false;
}

function getPeriodsHC($horHC1, $horHC2) {
	$periods = [];
	list($start, $end) = explode('-', $horHC1);
	$periods[] = [
			'start' => (int)$start,
			'end' => (int)$end
	];
	if ($horHC2 !== '') {
			list($start, $end) = explode('-', $horHC2);
			$periods[] = [
					'start' => (int)$start,
					'end' => (int)$end
			];
	}
	return $periods;
}

if (isset($_POST['tarifBase']) && isset($_POST['tarifHP']) && isset($_POST['horaireHC1']) && isset($_FILES['conso_file']) && file_exists($_FILES['conso_file']['tmp_name'])) {
    $consos = [];

    $sumBase = 0;
		$stats = [
			'tempo' => [
				'rouge' => [ 'costhp' => 0.0, 'costhc' => 0.0, 'consohp' => 0.0, 'consohc' => 0.0, 'consobydayhp' => 0.0, 'consobydayhc' => 0.0, 'days' => 0 ],
				'blanc' => [ 'costhp' => 0.0, 'costhc' => 0.0, 'consohp' => 0.0, 'consohc' => 0.0, 'consobydayhp' => 0.0, 'consobydayhc' => 0.0, 'days' => 0 ],
				'bleu'  => [ 'costhp' => 0.0, 'costhc' => 0.0, 'consohp' => 0.0, 'consohc' => 0.0, 'consobydayhp' => 0.0, 'consobydayhc' => 0.0, 'days' => 0 ],
				'total' => [ 'costhp' => 0.0, 'costhc' => 0.0, 'consohp' => 0.0, 'consohc' => 0.0, 'consobydayhp' => 0.0, 'consobydayhc' => 0.0, 'days' => 0 ]
			],
			'tempocorrected' => [
				'rouge' => [ 'costhp' => 0.0, 'costhc' => 0.0, 'consohp' => 0.0, 'consohc' => 0.0, 'consobydayhp' => 0.0, 'consobydayhc' => 0.0, 'days' => 0 ],
				'blanc' => [ 'costhp' => 0.0, 'costhc' => 0.0, 'consohp' => 0.0, 'consohc' => 0.0, 'consobydayhp' => 0.0, 'consobydayhc' => 0.0, 'days' => 0 ],
				'bleu'  => [ 'costhp' => 0.0, 'costhc' => 0.0, 'consohp' => 0.0, 'consohc' => 0.0, 'consobydayhp' => 0.0, 'consobydayhc' => 0.0, 'days' => 0 ],
				'total' => [ 'costhp' => 0.0, 'costhc' => 0.0, 'consohp' => 0.0, 'consohc' => 0.0, 'consobydayhp' => 0.0, 'consobydayhc' => 0.0, 'days' => 0 ]
			],
			'hchp' => [ 'costhp' => 0.0, 'costhc' => 0.0, 'consohp' => 0.0, 'consohc' => 0.0, 'consobydayhp' => 0.0, 'consobydayhc' => 0.0, 'days' => 0 ]
		];
    $nbMonths = 0;
    $prevMonth = null;
    $totalConso = 0;
		
    // Histo Tempo
    $tempoHistoJson = json_decode(file_get_contents('tempo.json'),
        true);
    foreach ($tempoHistoJson['dates'] as $item) {
        $tempoHisto[$item['date']] = $item['couleur'];
    }

    // Prepare conso
    if (($handle = fopen($_FILES['conso_file']['tmp_name'], "r")) !== false) {
        $hasHeader = false;
        while (($data = fgetcsv($handle, 1000, ";")) !== false) {
					  if (count($data) == 2) {
								list($date, $value) = $data;

								$sourceDate = trim(str_replace("﻿", '', $date));
								if (strlen($sourceDate) > 15 && $sourceDate[0] == '2' && $sourceDate[4] == '-' && $sourceDate[7] == '-' && $sourceDate[10] == 'T') {
										$newDate = DateTime::createFromFormat(DATE_ATOM, $sourceDate);
										
										if (str_contains($excludeDays, $newDate->format('d/m/Y')) == false) {
											$month = $newDate->format('n');
											if (!$prevMonth || $prevMonth !== $month) {
													$prevMonth = $month;
													$nbMonths++;
											}

											$consos[$newDate->format('U')] = [
													'date' => $newDate,
													'val' => $value,
											];
										}
								}
            }
        }
        fclose($handle);
    }
    ksort($consos);
    $consos = array_values($consos);
		
		if (count($consos) == 0) {
			echo "Fichier de consommation horaire incorrect.";
			exit;
		}

    $firstDay = $consos[0]['date'];
    $lastDay = $consos[count($consos) - 1]['date'];

    // Heures creuses
    $periodsHC = getPeriodsHC( $horaireHC1, $horaireHC2);
		$periodsTempoHC = getPeriodsHC( '2200-0600', '');
		
    $comparatif = [];
    $row = 0;
		$previousDay = '';
    while ($row < count($consos)) {
        $interval = 30;
        /** @var DateTime $currentDate */
        $currentDate = $consos[$row]['date'];
        $currentHour = (int)$currentDate->format('Hi');

        if (isset($consos[$row + 1])) {
            /** @var DateInterval $period */
            $period = $consos[$row + 1]['date']->diff($currentDate);
            $interval = (int)$period->format('%i');

            if ($interval === 0) {
                $interval = (int)$period->format('%h') * 60;
            }

            if ($interval === 0) {
                echo 'Interval of 0 on line '.$row.'<br />';
                echo '<pre>';
                var_dump($consos[$row]['date'], $consos[$row + 1]['date']);
                exit;
            }
        }

        $divisionHoraire = (60 / $interval);

        $valueKWH = (int)$consos[$row]['val'] / 1000 / $divisionHoraire;

        // Base
        $priceBase = $valueKWH * $tarifBase;
				
				// Heure creuse ?
				$isHC = isHC($periodsHC, $currentHour);
				$isTempoHC = isHC($periodsTempoHC, $currentHour);

        // Tempo
        $tempoDate = (int)$currentDate->format('Hi') > 600 ? (clone $currentDate) : (clone $currentDate)->sub(new DateInterval('P1D'));
        $couleurTempo = $tempoHisto[$tempoDate->format('Y-n-j')] ?? 'TEMPO_BLEU';
				if ($couleurTempo == 'TEMPO_BLEU') {
					$couleurTempo = 'bleu';
					$tarifTempo = $isTempoHC ? $tarifTempoBlueHC : $tarifTempoBlueHP;
				}
				if ($couleurTempo == 'TEMPO_BLANC') {
					$couleurTempo = 'blanc';
					$tarifTempo = $isTempoHC ? $tarifTempoWhiteHC : $tarifTempoWhiteHP;
				}
				if ($couleurTempo == 'TEMPO_ROUGE') {
					$couleurTempo = 'rouge';
					$tarifTempo = $isTempoHC ? $tarifTempoRedHC : $tarifTempoRedHP;
				}
        $priceTempo = $valueKWH * $tarifTempo;
				$isNewDate = $currentDate->format('Ymd') != $previousDay;
				$stats['tempo'][$couleurTempo]['conso'.($isTempoHC ? 'hc' : 'hp')] += $valueKWH;
				$stats['tempo'][$couleurTempo]['cost'.($isTempoHC ? 'hc' : 'hp')] += $priceTempo;
				$stats['tempo']['total']['conso'.($isTempoHC ? 'hc' : 'hp')] += $valueKWH;
				$stats['tempo']['total']['cost'.($isTempoHC ? 'hc' : 'hp')] += $priceTempo;
				if ($isNewDate) {
					$stats['tempo'][$couleurTempo]['days'] ++;
					$stats['tempo']['total']['days'] ++;
				}

        // HC/HP
        $tarifHCHP = $isHC ? $tarifHC : $tarifHP;
        $priceHCHP = $valueKWH * $tarifHCHP;
				
        $comparatif[] = [
            $currentDate->format(DATE_ATOM),
            $valueKWH,
            $tarifBase,
            $priceBase,
            $couleurTempo,
            $tarifTempo,
            $priceTempo,
            $isHC ? 'oui' : 'non',
						$isTempoHC ? 'oui' : 'non',
            $tarifHCHP,
            $priceHCHP,
        ];

        $sumBase += $priceBase;
				$stats['hchp']['cost'.($isHC ? 'hc' : 'hp')] += $priceHCHP;

        $totalConso += $valueKWH * 1000;

				$previousDay = $currentDate->format('Ymd');
        $row++;
    }
//    exit;

    $totalBase = $sumBase + $aboBase * $nbMonths;
    $totalTempo = $stats['tempo']['total']['costhp'] + $stats['tempo']['total']['costhc'] + $aboTempo * $nbMonths;
    $totalHCHP = $stats['hchp']['costhc'] + $stats['hchp']['costhp'] + $aboHCHP * $nbMonths;
		$stats['tempo']['rouge']['consobydayhp'] = $stats['tempo']['rouge']['days'] == 0 ? 0.0 : $stats['tempo']['rouge']['consohp'] / $stats['tempo']['rouge']['days'];
		$stats['tempo']['rouge']['consobydayhc'] = $stats['tempo']['rouge']['days'] == 0 ? 0.0 : $stats['tempo']['rouge']['consohc'] / $stats['tempo']['rouge']['days'];
		$stats['tempo']['blanc']['consobydayhp'] = $stats['tempo']['blanc']['days'] == 0 ? 0.0 : $stats['tempo']['blanc']['consohp'] / $stats['tempo']['blanc']['days'];
		$stats['tempo']['blanc']['consobydayhc'] = $stats['tempo']['blanc']['days'] == 0 ? 0.0 : $stats['tempo']['blanc']['consohc'] / $stats['tempo']['blanc']['days'];
		$stats['tempo']['bleu']['consobydayhp'] = $stats['tempo']['bleu']['days'] == 0 ? 0.0 : $stats['tempo']['bleu']['consohp'] / $stats['tempo']['bleu']['days'];
		$stats['tempo']['bleu']['consobydayhc'] = $stats['tempo']['bleu']['days'] == 0 ? 0.0 : $stats['tempo']['bleu']['consohc'] / $stats['tempo']['bleu']['days'];
		$stats['tempo']['total']['consobydayhp'] = $stats['tempo']['total']['days'] == 0 ? 0.0 : $stats['tempo']['total']['consohp'] / $stats['tempo']['total']['days'];
		$stats['tempo']['total']['consobydayhc'] = $stats['tempo']['total']['days'] == 0 ? 0.0 : $stats['tempo']['total']['consohc'] / $stats['tempo']['total']['days'];
		
		$aboTempoCorrected = 0;
		$totalTempoCorrected = 0;
		$stdTempoRed = 22.0;
		$stdTempoWhite = 43.0;
		$stdTempoBlue = 300.24219;	// average duration of one year is 365.24219
		$isTempoCorrected = ($stats['tempo']['rouge']['days'] > $stdTempoRed/5 
											&& $stats['tempo']['blanc']['days'] > $stdTempoWhite/5 
											&& $stats['tempo']['bleu']['days'] > $stdTempoBlue/5);	// accept only significant values
		if ($isTempoCorrected) {
      $stdTempoAllColors = $stdTempoRed + $stdTempoWhite + $stdTempoBlue;			
			$nbTempoAllColors = 0.0 + $stats['tempo']['rouge']['days'] + $stats['tempo']['blanc']['days'] + $stats['tempo']['bleu']['days'];
			$stats['tempocorrected']['rouge']['days'] = ($stdTempoRed/$stdTempoAllColors) * $nbTempoAllColors;
			$stats['tempocorrected']['rouge']['costhp'] = $stats['tempo']['rouge']['costhp'] * $stats['tempocorrected']['rouge']['days'] / $stats['tempo']['rouge']['days'];
			$stats['tempocorrected']['rouge']['costhc'] = $stats['tempo']['rouge']['costhc'] * $stats['tempocorrected']['rouge']['days'] / $stats['tempo']['rouge']['days'];
			$stats['tempocorrected']['rouge']['consohp'] = $stats['tempo']['rouge']['consohp'] * $stats['tempocorrected']['rouge']['days'] / $stats['tempo']['rouge']['days'];
			$stats['tempocorrected']['rouge']['consohc'] = $stats['tempo']['rouge']['consohc'] * $stats['tempocorrected']['rouge']['days'] / $stats['tempo']['rouge']['days'];
			$stats['tempocorrected']['blanc']['days'] = ($stdTempoWhite/$stdTempoAllColors) * $nbTempoAllColors;
			$stats['tempocorrected']['blanc']['costhp'] = $stats['tempo']['blanc']['costhp'] * $stats['tempocorrected']['blanc']['days'] / $stats['tempo']['blanc']['days'];
			$stats['tempocorrected']['blanc']['costhc'] = $stats['tempo']['blanc']['costhc'] * $stats['tempocorrected']['blanc']['days'] / $stats['tempo']['blanc']['days'];
			$stats['tempocorrected']['blanc']['consohp'] = $stats['tempo']['blanc']['consohp'] * $stats['tempocorrected']['blanc']['days'] / $stats['tempo']['blanc']['days'];
			$stats['tempocorrected']['blanc']['consohc'] = $stats['tempo']['blanc']['consohc'] * $stats['tempocorrected']['blanc']['days'] / $stats['tempo']['blanc']['days'];
			$stats['tempocorrected']['bleu']['days'] = ($stdTempoBlue/$stdTempoAllColors) * $nbTempoAllColors;
			$stats['tempocorrected']['bleu']['costhp'] = $stats['tempo']['bleu']['costhp'] * $stats['tempocorrected']['bleu']['days'] / $stats['tempo']['bleu']['days'];
			$stats['tempocorrected']['bleu']['costhc'] = $stats['tempo']['bleu']['costhc'] * $stats['tempocorrected']['bleu']['days'] / $stats['tempo']['bleu']['days'];
			$stats['tempocorrected']['bleu']['consohp'] = $stats['tempo']['bleu']['consohp'] * $stats['tempocorrected']['bleu']['days'] / $stats['tempo']['bleu']['days'];
			$stats['tempocorrected']['bleu']['consohc'] = $stats['tempo']['bleu']['consohc'] * $stats['tempocorrected']['bleu']['days'] / $stats['tempo']['bleu']['days'];
			$stats['tempocorrected']['total']['days'] = $stats['tempocorrected']['rouge']['days'] 
																								+ $stats['tempocorrected']['blanc']['days']
																								+ $stats['tempocorrected']['bleu']['days'];
			$stats['tempocorrected']['total']['costhp'] = $stats['tempocorrected']['rouge']['costhp']
																									+ $stats['tempocorrected']['blanc']['costhp']
																									+ $stats['tempocorrected']['bleu']['costhp'];
			$stats['tempocorrected']['total']['costhc'] = $stats['tempocorrected']['rouge']['costhc'] 
																									+ $stats['tempocorrected']['blanc']['costhc'] 
																									+ $stats['tempocorrected']['bleu']['costhc'];
			$stats['tempocorrected']['total']['consohp'] = $stats['tempocorrected']['rouge']['consohp']
																									+ $stats['tempocorrected']['blanc']['consohp']
																									+ $stats['tempocorrected']['bleu']['consohp'];
			$stats['tempocorrected']['total']['consohc'] = $stats['tempocorrected']['rouge']['consohc'] 
																									+ $stats['tempocorrected']['blanc']['consohc'] 
																									+ $stats['tempocorrected']['bleu']['consohc'];
			$aboTempoCorrected = $aboTempo;
			$totalTempoCorrected = $stats['tempocorrected']['total']['costhp'] + $stats['tempocorrected']['total']['costhc'] + $aboTempo * $nbMonths;
		}

    if (isset($_POST['export']) && $_POST['export'] === 'oui') {
        $fp = fopen('php://memory', 'w');
        fputcsv($fp, [
            'Date',
            'Consommation en kWh',
            'Tarif kWh Base',
            'Prix Base',
            'Couleur Tempo',
            'Tarif kWh Tempo',
            'Total Tempo',
            'HC HC/HP',
						'HC Tempo',
            'Tarif kWh HC/HP',
            'Total HC/HP',
        ], ';');

        foreach ($comparatif as $fields) {
            fputcsv($fp, $fields, ';');
        }
        fseek($fp, 0);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="detail.csv";');
        fpassthru($fp);
        fclose($fp);
        exit;
    } else {
			  $comment = '';
        $totalTable = '
            <h3>
                Période du '.$consos[0]['date']->format('d/m/Y').' au '.$consos[count($consos) - 1]['date']->format('d/m/Y').'
                 - Consommation totale : '.($totalConso / 1000).' kWh
            </h3>
            <table class="table table-striped">
                <tr>
                    <th></th>
                    <th>Abonnement ('.$nbMonths.' mois)</th>
                    <th>Consommation période</th>
                    <th>Total période</th>
                    <th>Economie</th>
                </tr>
                <tr>
                    <th>Base</th>
                    <td>'.number_format($aboBase * $nbMonths, 2).' €</td>
                    <td>'.number_format($sumBase, 2).' €</td>
                    <td>'.number_format($totalBase, 2).' €</td>
                    <td></td>
                </tr>
                <tr>
                    <th>Tempo</th>
                    <td>'.number_format($aboTempo * $nbMonths, 2).' €</td>
                    <td>'.number_format($stats['tempo']['total']['costhp'] + $stats['tempo']['total']['costhc'], 2).' €</td>
                    <td>'.number_format($totalTempo, 2).' €</td>
                    <td>'.number_format(100 - (100 * $totalTempo / $totalBase), 2).'%</td>
                </tr>';
				if ($isTempoCorrected) {
					$totalTable .= '
                <tr>
                    <th>Tempo corrigé (1)</th>
                    <td>'.number_format($aboTempoCorrected * $nbMonths, 2).' €</td>
                    <td>'.number_format($stats['tempocorrected']['total']['costhp'] + $stats['tempocorrected']['total']['costhc'], 2).' €</td>
                    <td>'.number_format($totalTempoCorrected, 2).' €</td>
                    <td>'.number_format(100 - (100 * $totalTempoCorrected / $totalBase), 2).'%</td>
                </tr>';
				}
				$totalTable .= '
                <tr>
                    <th>Heures Creuses</th>
                    <td>'.number_format($aboHCHP * $nbMonths, 2).' €</td>
                    <td>'.number_format($stats['hchp']['costhc'] + $stats['hchp']['costhp'], 2).' €</td>
                    <td>'.number_format($totalHCHP, 2).' €</td>
                    <td>'.number_format(100 - (100 * $totalHCHP / $totalBase), 2).'%</td>
                </tr>
            </table>
            ';
				$detailTempoTable = '
						<hr class="hr hr-blurry" />
						<h4>
                Détail de la consommation Tempo
            </h4>
						<table class="table table-striped">
                <tr>
                    <th></th>
                    <th>Rouge</th>
                    <th>Blanc</th>
                    <th>Bleu</th>
										<th>Total</th>
                </tr>
								<tr>
                    <th>Tempo - Nombre de jours</th>
                    <td>'.number_format($stats['tempo']['rouge']['days'], 0).'</td>
                    <td>'.number_format($stats['tempo']['blanc']['days'], 0).'</td>
                    <td>'.number_format($stats['tempo']['bleu']['days'], 0).'</td>
                    <td>'.number_format($stats['tempo']['total']['days'], 0).'</td>
                </tr>
								<tr>
                    <th>Tempo - Consommation journalière (kWh/jour)</th>
                    <td>'.number_format($stats['tempo']['rouge']['consobydayhp'] + $stats['tempo']['rouge']['consobydayhc'], 2).'</td>
                    <td>'.number_format($stats['tempo']['blanc']['consobydayhp'] + $stats['tempo']['blanc']['consobydayhc'], 2).'</td>
                    <td>'.number_format($stats['tempo']['bleu']['consobydayhp'] + $stats['tempo']['bleu']['consobydayhc'], 2).'</td>
                    <td>'.'</td>
                </tr>
								<tr>
                    <th>Tempo - Coût HP</th>
                    <td>'.number_format($stats['tempo']['rouge']['costhp'], 2).' €</td>
                    <td>'.number_format($stats['tempo']['blanc']['costhp'], 2).' €</td>
                    <td>'.number_format($stats['tempo']['bleu']['costhp'], 2).' €</td>
                    <td>'.number_format($stats['tempo']['total']['costhp'], 2).' €</td>
                </tr>
								<tr>
                    <th>Tempo - Coût HC</th>
                    <td>'.number_format($stats['tempo']['rouge']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempo']['blanc']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempo']['bleu']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempo']['total']['costhc'], 2).' €</td>
                </tr>
								<tr>
                    <th>Tempo - Coût HP + HC</th>
                    <td>'.number_format($stats['tempo']['rouge']['costhp'] + $stats['tempo']['rouge']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempo']['blanc']['costhp'] + $stats['tempo']['blanc']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempo']['bleu']['costhp'] + $stats['tempo']['bleu']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempo']['total']['costhp'] + $stats['tempo']['total']['costhc'], 2).' €</td>
                </tr>
						</table>
					';
				if ($isTempoCorrected) {
					$detailTempoTable .= '
						<hr class="hr hr-blurry" />
						<h4>
                Détail de la consommation Tempo corrigé (1)
            </h4>
						<table class="table table-striped">
                <tr>
                    <th></th>
                    <th>Rouge</th>
                    <th>Blanc</th>
                    <th>Bleu</th>
										<th>Total</th>
                </tr>
								<tr>
                    <th>Tempo corrigé (1) - Nombre de jours</th>
                    <td>'.number_format($stats['tempocorrected']['rouge']['days'], 0).'</td>
                    <td>'.number_format($stats['tempocorrected']['blanc']['days'], 0).'</td>
                    <td>'.number_format($stats['tempocorrected']['bleu']['days'], 0).'</td>
                    <td>'.number_format($stats['tempocorrected']['total']['days'], 0).'</td>
                </tr>
								<tr>
                    <th>Tempo corrigé (1) - Coût HP</th>
                    <td>'.number_format($stats['tempocorrected']['rouge']['costhp'], 2).' €</td>
                    <td>'.number_format($stats['tempocorrected']['blanc']['costhp'], 2).' €</td>
                    <td>'.number_format($stats['tempocorrected']['bleu']['costhp'], 2).' €</td>
                    <td>'.number_format($stats['tempocorrected']['total']['costhp'], 2).' €</td>
                </tr>
								<tr>
                    <th>Tempo corrigé (1) - Coût HC</th>
                    <td>'.number_format($stats['tempocorrected']['rouge']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempocorrected']['blanc']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempocorrected']['bleu']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempocorrected']['total']['costhc'], 2).' €</td>
                </tr>
								<tr>
                    <th>Tempo corrigé (1) - Coût HP + HC</th>
                    <td>'.number_format($stats['tempocorrected']['rouge']['costhp'] + $stats['tempocorrected']['rouge']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempocorrected']['blanc']['costhp'] + $stats['tempocorrected']['blanc']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempocorrected']['bleu']['costhp'] + $stats['tempocorrected']['bleu']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['tempocorrected']['total']['costhp'] + $stats['tempocorrected']['total']['costhc'], 2).' €</td>
                </tr>
						</table>	
					';
				}
				
				$detailHCHPTable = '
						<hr class="hr hr-blurry" />
						<h4>
              Détail de la consommation Heures Creuses
            </h4>
						<table class="table table-striped">
                <tr>
                    <th></th>
                    <th>Heures pleines</th>
                    <th>Heures creuses</th>
										<th>Total</th>
                </tr>
								<tr>
                    <th>Heures Creuses - Coût</th>
                    <td>'.number_format($stats['hchp']['costhp'], 2).' €</td>
                    <td>'.number_format($stats['hchp']['costhc'], 2).' €</td>
                    <td>'.number_format($stats['hchp']['costhp']+ $stats['hchp']['costhc'] , 2).' €</td>
                </tr>
						</table>
					';
				if ($isTempoCorrected) {
					$comment .= '<footer class="blockquote-footer">
											(1) - Tempo corrigé recalcule l\'option pour obtenir le nombre de jours annuels contractuels : 22 jours rouges, 33 jours blancs et 300 ou 301 jours bleus
											</footer>
					';
				}
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comparatif conso electrique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
        crossorigin="anonymous"></script>

<div class="container">
    <h2>Comparatif de facture Base / Heures Creuses / Tempo</h2>
		
    <form id="parametersform" action="/" method="POST" enctype="multipart/form-data">
		
        <fieldset>
            <legend>Consommation</legend>
						<div class="row mb-3">
                <div class="col">
                    <label for="conso_file" class="form-label">Fichier de consommation horaire (CSV)</label>
                    <input type="file" class="form-control" name="conso_file" id="conso_file">
                    <p class="small">
                        Fichier CSV récupéré sur <a href="https://mon-compte-particulier.enedis.fr/suivi-de-mesures">Enedis</a>
                    </p>
                </div>
                <div class="col">
                    <label for="excludeDays" class="form-label">Jours à exclure</label>
                    <input type="text" class="form-control" name="excludeDays" id="excludeDays" value="<?php
                    echo $excludeDays; ?>" placeholder="">
										<p class="small">Format : <code>JJ/MM/AAAA;...</code><br/>Exemple :
                        <code>05/02/2022;06/02/2022;13/02/2022</code></p>
                </div>
            </div>
        </fieldset>
		
        <fieldset>
            <legend>Option Base</legend>
            <div class="row mb-3">
                <div class="col">
                    <label for="aboBase" class="form-label">Abonnement mensuel base (€ TTC)</label>
                    <input type="text" class="form-control" name="aboBase" id="aboBase" value="<?php
                    echo $aboBase; ?>" placeholder="15">
                </div>
                <div class="col">
                    <label for="tarifBase" class="form-label">Tarif base (€ TTC)</label>
                    <input type="text" class="form-control" name="tarifBase" id="tarifBase" value="<?php
                    echo $tarifBase; ?>" placeholder="0.1659">
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Option Heures Creuses</legend>
            <div class="row mb-3">
                <div class="col">
                    <label for="aboHCHP" class="form-label">Abonnement mensuel HC/HP (€ TTC)</label>
                    <input type="text" class="form-control" name="aboHCHP" id="aboHCHP" value="<?php
                    echo $aboHCHP; ?>" placeholder="">
                </div>
                <div class="col">
                    <label for="tarifHP" class="form-label">Tarif HP (€ TTC)</label>
                    <input type="text" class="form-control" name="tarifHP" id="tarifHP" value="<?php
                    echo $tarifHP; ?>" placeholder="">
                </div>
                <div class="col">
                    <label for="tarifHC" class="form-label">Tarif HC (€ TTC)</label>
                    <input type="text" class="form-control" name="tarifHC" id="tarifHC" value="<?php
                    echo $tarifHC; ?>" placeholder="">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label for="horaireHC1" class="form-label">Horaire HC 1</label>
                    <input type="text" class="form-control" name="horaireHC1" id="horaireHC1" value="<?php
                    echo $horaireHC1; ?>" placeholder="">
                    <p class="small">Format : <code>début[hhmm]-fin[hhmm]</code>.<br/>Exemple :
                        <code>2200-0600</code></p>
                </div>
                <div class="col">
                    <label for="horaireHC2" class="form-label">Horaire HC 2</label>
                    <input type="text" class="form-control" name="horaireHC2" id="horaireHC2" value="<?php
                    echo $horaireHC2; ?>" placeholder="">
                    <p class="small">Format : <code>début[hhmm]-fin[hhmm]</code>.<br/>Exemple :
                        <code>1230-1430</code></p>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Option Tempo</legend>
            <div class="row mb-3">
                <div class="col">
                    <label for="aboTempo" class="form-label">Abonnement mensuel Tempo (€ TTC)</label>
                    <input type="text" class="form-control" name="aboTempo" id="aboTempo" value="<?php
                    echo $aboTempo; ?>" placeholder="15">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label for="tarifTempoRedHP" class="form-label">Tarif Tempo Rouge HP (€ TTC)</label>
                    <input type="text" class="form-control" name="tarifTempoRedHP" id="tarifTempoRedHP" value="<?php
                    echo $tarifTempoRedHP; ?>" placeholder="">
                </div>
                <div class="col">
                    <label for="tarifTempoWhiteHP" class="form-label">Tarif Tempo Blanc HP (€ TTC)</label>
                    <input type="text" class="form-control" name="tarifTempoWhiteHP" id="tarifTempoWhiteHP" value="<?php
                    echo $tarifTempoWhiteHP; ?>" placeholder="">
                </div>
                <div class="col">
                    <label for="tarifTempoBlueHP" class="form-label">Tarif Tempo Bleu HP (€ TTC)</label>
                    <input type="text" class="form-control" name="tarifTempoBlueHP" id="tarifTempoBlueHP" value="<?php
                    echo $tarifTempoBlueHP; ?>" placeholder="">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label for="tarifTempoRedHC" class="form-label">Tarif Tempo Rouge HC (€ TTC)</label>
                    <input type="text" class="form-control" name="tarifTempoRedHC" id="tarifTempoRedHC" value="<?php
                    echo $tarifTempoRedHC; ?>" placeholder="">
                </div>
                <div class="col">
                    <label for="tarifTempoWhiteHC" class="form-label">Tarif Tempo Blanc HC (€ TTC)</label>
                    <input type="text" class="form-control" name="tarifTempoWhiteHC" id="tarifTempoWhiteHC" value="<?php
                    echo $tarifTempoWhiteHC; ?>" placeholder="">
                </div>
                <div class="col">
                    <label for="tarifTempoBlueHC" class="form-label">Tarif Tempo Bleu HC (€ TTC)</label>
                    <input type="text" class="form-control" name="tarifTempoBlueHC" id="tarifTempoBlueHC" value="<?php
                    echo $tarifTempoBlueHC; ?>" placeholder="">
                </div>
            </div>
        </fieldset>
				
				<hr class="hr hr-blurry" />

        <div class="mb-3">
            <label for="export">
                <input type="checkbox" id="export" name="export" value="oui"> Télécharger le détail en CSV
            </label>
        </div>

        <div class="mb-3">
            <button type="submit" class="btn btn-primary mb-3">Calculer</button>
        </div>
    </form>

    <?php
    if (isset($totalTable)) {
        echo $totalTable;
				if (isset($detailTempoTable)) {
					echo $detailTempoTable;
				}
				if (isset($detailHCHPTable)) {
					echo $detailHCHPTable;
				}
				if (isset($comment)) {
					echo $comment;
				}
				echo '
					<script type="text/javascript">
						document.getElementById("parametersform").style.display = "none";
					</script>
				';
    } ?>


    <p class="text-end small"><a href="https://github.com/marolve/tempo-comparatif">Voir le code sur GitHub</a></p>
</div>

</body>
</html>

