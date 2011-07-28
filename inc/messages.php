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

include_spip('inc/filtres');
include_spip('base/abstract_sql');

if (!defined('_EMAIL_GENERAL'))
	define('_EMAIL_GENERAL','general'); // permet aux admin d'envoyer un email a tout le monde

/**
 * Fonction generique de verification de la saisie
 * lors de l'envoi d'un message ou de recommander
 *
 * @param array $obligatoires
 * @return array
 */
function messagerie_verifier($obligatoires = array()){
	$erreurs = array();
	foreach($obligatoires as $obli)
		if (!_request($obli))
			$erreurs[$obli] = (isset($erreurs[$obli])?$erreurs[$obli]:'') . _T('formulaires:info_obligatoire_rappel');

	$dests = _request('destinataires');
	
	if (!count($dests)
	  AND !count(	$dests = pipeline('messagerie_destiner',$dests)))
			$erreurs[$obli='destinataire'] = (isset($erreurs[$obli])?$erreurs[$obli]:'') . _T('formulaires:info_obligatoire_rappel');
	
	return $erreurs;
}

/**
 * Selectionner les destinataires en distinguant emails et id_auteur
 *
 * @param unknown_type $dests
 * @return unknown
 */
function messagerie_destiner($dests){
	$dests = pipeline('messagerie_destiner',$dests);
	
	// separer les destinataires auteur des destinataires email
	$auteurs_dest = array();
	$email_dests = array();
	foreach ($dests as $id){
		// il se peut que l'id recupere l'ancre qui suit avec certains ie ... :(
		if (preg_match(',[0-9]+#[a-z_0-9]+,',$id))
			$id = intval($id);
		if (is_numeric($id))
			$auteurs_dest[] = $id;
		elseif ($id!=_EMAIL_GENERAL)
			$email_dests[] = $id;
	}
	if (count($email_dests)) {
		// retrouver les id des emails
		$res = sql_select('id_auteur,email','spip_auteurs',sql_in('email', $email_dests).')');
		$auteurs_dest_found = array();
		while ($row = spip_fetch_array($res)){
			$auteurs_dest_found[] = $row['id_auteur'];
		}
		$auteurs_dest = array_merge($auteurs_dest,$auteurs_dest_found);
	}
	return array($auteurs_dest,$email_dests);
}

/**
 * Envoyer un message par la messagerie interne
 *
 * @param string $objet
 * @param string $texte
 * @param array $auteurs_dest
 * @param bool $type
 * @return int
 */
function messagerie_messager($objet, $texte, $auteurs_dest=array(),$type = ''){
	$type = substr($type,0,6);
	if (!in_array($type,array('normal','genera','pb','affich')))
		$type = 'normal';
	$out = false;
	if (count($auteurs_dest) OR $general){
		// envoyons le message
		$id_message = sql_insertq('spip_messages',array(
		'titre' => safehtml($objet),
		'texte' => safehtml($texte),
		'type' => $type,
		'date_heure' => 'NOW()',
		'date_fin' => 'NOW()',
		'rv' => 'non',
		'statut' => 'publie',
		'id_auteur' => $GLOBALS['visiteur_session']['id_auteur'],
		));
		
		if ($id_message) {
			$insert = array();
			if (!$general) {
				foreach($auteurs_dest as $id)
					$insert[] = array('id_objet'=>$id_message,'objet'=>'message','id_auteur'=>$id,'vu'=>'non');
			}
			else {
				$res = sql_select('id_auteur','spip_auteurs');
				while ($row = sql_fetch($res))
					$insert[] = array('id_objet'=>$id_message,'objet'=>'message','id_auteur'=>$row['id_auteur'],'vu'=>'non');
			}
			sql_insertq_multi('spip_auteurs_liens',$insert);

			$out = $id_message;			
		}
	}
	return $out;
}

/**
 * Envoyer un message par mail
 *
 * @param string $objet
 * @param string $texte
 * @param array $emails_dest
 * @return bool
 */
function messagerie_mailer($objet, $texte, $emails_dest=array()){
	if (count($emails_dest)) {
		$from = sql_getfetsel('email','spip_auteurs','id_auteur='.intval($GLOBALS['visiteur_session']['id_auteur']));
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		foreach($emails_dest as $email)
			$envoyer_mail($email,$objet,$texte,$from);
		return true;
	}
	return false;
}

/**
 * Marquer un message comme lu
 *
 * @param int $id_auteur
 * @param array $liste
 */
function messagerie_marquer_lus($id_auteur,$liste){
	$liste = array_map('intval',$liste);
	sql_updateq('spip_auteurs_liens',array('vu'=>'oui'),array('id_auteur='.intval($id_auteur),"objet='message'",sql_in('id_message',$liste)));
	include_spip('inc/invalideur');
	suivre_invalideur("message/".implode(',',$liste));
}

/**
 * Marquer un message comme non lu
 *
 * @param int $id_auteur
 * @param array $liste
 */
function messagerie_marquer_non_lus($id_auteur,$liste){
	$liste = array_map('intval',$liste);
	sql_updateq('spip_auteurs_liens',array('vu'=>'non'),array('id_auteur='.intval($id_auteur),"objet='message'",sql_in('id_message',$liste)));
	include_spip('inc/invalideur');
	suivre_invalideur("message/".implode(',',$liste));
}

/**
 * Effacer un message
 *
 * @param int $id_auteur
 * @param array $liste
 */
function messagerie_effacer($id_auteur,$liste){
	$liste = array_map('intval',$liste);
	sql_updateq('spip_auteurs_liens',array('vu'=>'pou'),array('id_auteur='.intval($id_auteur),"objet='message'",sql_in('id_message',$liste)));
	include_spip('inc/invalideur');
	suivre_invalideur("message/".implode(',',$liste));
}

?>
