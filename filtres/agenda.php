<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2011                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined('_ECRIRE_INC_VERSION')) return;

// http://doc.spip.org/@agenda_connu
function agenda_connu($type)
{
  return in_array($type, array('jour','mois','semaine','periode')) ? ' ' : '';
}


// Cette fonction memorise dans un tableau indexe par son 5e arg
// un evenement decrit par les 4 autres (date, descriptif, titre, URL).
// Appellee avec une date nulle, elle renvoie le tableau construit.
// l'indexation par le 5e arg autorise plusieurs calendriers dans une page

// http://doc.spip.org/@agenda_memo
function agenda_memo($date=0 , $descriptif='', $titre='', $url='', $cal='')
{
  static $agenda = array();
  if (!$date) return $agenda;
  $idate = date_ical($date);
  $cal = trim($cal); // func_get_args (filtre alterner) rajoute \n !!!!
  $agenda[$cal][(date_anneemoisjour($date))][] =  array(
			'CATEGORIES' => $cal,
			'DTSTART' => $idate,
			'DTEND' => $idate,
                        'DESCRIPTION' => texte_script($descriptif),
                        'SUMMARY' => texte_script($titre),
                        'URL' => $url);
  // toujours retourner vide pour qu'il ne se passe rien
  return "";
}

// Cette fonction recoit:
// - un nombre d'evenements,
// - une chaine a afficher si ce nombre est nul,
// - un type de calendrier
// -- et une suite de noms N.
// Elle demande a la fonction precedente son tableau
// et affiche selon le type les elements indexes par N dans ce tableau.
// Si le suite de noms est vide, tout le tableau est pris
// Ces noms N sont aussi des classes CSS utilisees par http_calendrier_init
// Cette fonction recupere aussi par _request les parametres
// jour, mois, annee, echelle, partie_cal (a ameliorer)

// http://doc.spip.org/@agenda_affiche
function agenda_affiche($i)
{
	include_spip('inc/agenda');
	$args = func_get_args();
	// date ou nombre d'evenements (on pourrait l'afficher)
	$nb = array_shift($args);
	$evt = array_shift($args);
	$type = array_shift($args);
		spip_log("evt $nb");
	if (!$nb) {
		$d = array(time());
	} else {
		$agenda = agenda_memo(0);
		$evt = array();
		foreach (($args ? $args : array_keys($agenda)) as $k) {
			if (is_array($agenda[$k]))
				foreach($agenda[$k] as $d => $v) {
					$evt[$d] = $evt[$d] ? (array_merge($evt[$d], $v)) : $v;
				}
		}
		$d = array_keys($evt);
	}
	if (count($d)){
		$mindate = min($d);
		$start = strtotime($mindate);
	} else {
		$mindate = ($j=_request('jour')) * ($m=_request('mois')) * ($a=_request('annee'));
  		if ($mindate)
			$start = mktime(0,0,0, $m, $j, $a);
  		else $start = mktime(0,0,0);
	}
	if ($type != 'periode')
		$evt = array('', $evt);
	else {
		$min = substr($mindate,6,2);
		$max = $min + ((strtotime(max($d)) - $start) / (3600 * 24));
		if ($max < 31) $max = 0;
		$evt = array('', $evt, $min, $max);
		$type = 'mois';
	}
	return http_calendrier_init($start, $type,  _request('echelle'), _request('partie_cal'), self('&'), $evt);
}
