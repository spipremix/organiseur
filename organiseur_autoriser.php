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

/**
 * Fonction vide pour charger ce fichier sans declencher de warning
 * @return void
 */
function organiseur_autoriser(){}

function autoriser_calendrier_menu_dist($faire, $type='', $id=0, $qui = NULL, $opt = NULL){
	if($GLOBALS['meta']['messagerie_agenda'] == 'oui')
		return true;
	return false;
}

function autoriser_messagerie_menu_dist($faire, $type='', $id=0, $qui = NULL, $opt = NULL){
	if($GLOBALS['meta']['messagerie_agenda'] == 'oui')
		return true;
	return false;
}

function autoriser_message_modifier_dist($faire, $type='', $id=0, $qui = NULL, $opt = NULL){
	$row = sql_fetsel('statut,type,id_auteur','spip_messages','id_message='.intval($id));
	if ($row['id_auteur']!=$qui['id_auteur'])
		return false;
	if ($row['statut']=='redac' OR $row['type']=='pb')
		return true;
	return false;
}

function autoriser_message_dater_dist($faire, $type='', $id=0, $qui = NULL, $opt = NULL){
	return false;
}

function autoriser_message_voir_dist($faire, $type='', $id=0, $qui = NULL, $opt = NULL){
	if (!intval($qui['id_auteur']))
		return false;
	// message annonce ou message dont $qui est l'auteur : droit de le voir
	if (sql_countsel('spip_messages','id_message='.intval($id).' AND (type=\'affich\' OR id_auteur='.intval($qui['id_auteur']).')'))
		return true;
	// message dont $qui est destinataire
	if (sql_countsel('spip_auteurs_liens','objet=\'message\' AND id_objet='.intval($id)." AND id_auteur=".intval($qui['id_auteur'])))
		return true;

	return false;
}
