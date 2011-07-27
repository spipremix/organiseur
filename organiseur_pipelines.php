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
 * Lister les tables a ne pas inclure dans un export de BDD
 * ici se ramener a tester l'admin restreint est un abus
 * car cela presume qu'un admin restreint ne peut pas faire de sauvegarde
 * complete, alors que l'intention est d'exclure les messages
 * des sauvegardes partielles que peuvent realiser les admin restreint
 *
 * *a revoir*
 *
 * @param array $EXPORT_tables_noexport
 * @return array
 */
function organiseur_lister_tables_noexport($EXPORT_tables_noexport){
	if (!$GLOBALS['connect_toutes_rubriques']){
		$EXPORT_tables_noexport[]='spip_messages';
		#$EXPORT_tables_noexport[]='spip_auteurs_liens'; // where objet='message'
	}
	return $EXPORT_tables_noexport;
}

/**
 * Optimiser les liens morts dans la base de donnees
 *
 * @param array $flux
 * @return array
 */
function organiseur_optimiser_base_disparus($flux){
	//
	// Messages prives
	//

	# supprimer les messages lies a un auteur disparu
	$res = sql_select("M.id_message AS id",
		      "spip_messages AS M
		        LEFT JOIN spip_auteurs AS A
		          ON A.id_auteur=M.id_auteur",
			"A.id_auteur IS NULL");

	$flux['data'] += optimiser_sansref('spip_messages', 'id_message', $res);

	return $flux;
}

/**
 * Generer les alertes message recu a destination de l'auteur
 * concerne par l'appel
 *
 * @param array $flux
 * @return array
 */
function organiseur_alertes_auteur($flux) {

	$id_auteur = $flux['args']['id_auteur'];

	$result_messages = sql_allfetsel("M.id_message", "spip_messages AS M LEFT JOIN spip_auteurs_liens AS L ON (L.objet='message' AND L.id_objet=M.id_message)", "L.id_auteur=".intval($id_auteur)." AND vu='non' AND statut='publie' AND type='normal'");
	$total_messages = count($result_messages);
	if ($total_messages == 1) {
		$row = $result_messages[0];
		$ze_message=$row['id_message'];
		$flux['data'][] = "<a href='" . generer_url_ecrire("message","id_message=$ze_message") . "' class='ligne_foncee'>"._T('info_nouveau_message')."</a>";
	} elseif ($total_messages > 1)
		$flux['data'][] = "<a href='" . generer_url_ecrire("messagerie") . "' classe='ligne_foncee'>"._T('info_nouveaux_messages', array('total_messages' => $total_messages))."</a>";

	return $flux;
}

/**
 * Afficher les interventions et objets en lien
 * avec un auteur (sur sa page)
 *
 * @param array $flux
 * @return array
 */
function organiseur_affiche_auteurs_interventions($flux){

	if ($id_auteur = intval($flux['args']['id_auteur'])){
		include_spip('inc/message_select');
		// Messages de l'auteur et discussions en cours
		if ($GLOBALS['meta']['messagerie_agenda'] != 'non'
		AND $id_auteur != $GLOBALS['visiteur_session']['id_auteur']
		AND autoriser('ecrire', '', '', $flux['args']['auteur'])
		) {
			include_spip('inc/presentation');
			$out = "<div class='nettoyeur'>&nbsp;</div>";
			$out .= debut_cadre_couleur('', true);

			$vus = array();

			$out .= afficher_ses_messages('<b>' . _T('info_discussion_cours') . '</b>', ", spip_auteurs_liens AS A, spip_auteurs_liens AS D", "A.id_auteur=".intval($GLOBALS['visiteur_session']['id_auteur'])." AND D.id_auteur=".intval($id_auteur)." AND statut='publie' AND type='normal' AND rv!='oui' AND A.objet='message' AND A.id_objet=M.id_message AND D.objet='message' AND D.id_objet=M.id_message", $vus, false, false);
			$out .= afficher_ses_messages('<b>' . _T('info_vos_rendez_vous') . '</b>', ", spip_auteurs_liens AS A, spip_auteurs_liens AS D", "A.id_auteur=".intval($GLOBALS['visiteur_session']['id_auteur'])." AND D.id_auteur=".intval($id_auteur)." AND statut='publie' AND type='normal' AND rv='oui' AND date_fin > ".sql_quote(date('Y-m-d H:i:s'))." AND A.objet='message' AND A.id_objet=M.id_message AND D.objet='message' AND D.id_objet=M.id_message", $vus, false, false);
			$out .= icone_horizontale(_T('info_envoyer_message_prive'), generer_action_auteur("editer_message","normal/$id_auteur"),"message-24.png");
			$out .= fin_cadre_couleur(true);

		  $flux['data'] .= $out;
		}
	}
  return $flux;
}

/**
 * Declarer les metas de configuration de l'agenda/messagerie
 * @param array $metas
 * @return array
 */
function organiseur_configurer_liste_metas($metas){
	$metas['messagerie_agenda'] = 'oui';
	return $metas;
}

/**
 * Inserer la css de l'agenda dans l'espace prive (hum)
 * @param string $head
 * @return string
 */
function organiseur_header_prive($head){
	// CSS calendrier
	if ($GLOBALS['meta']['messagerie_agenda'] != 'non')
		$head .= '<link rel="stylesheet" type="text/css" href="'
		  . url_absolue(find_in_path('calendrier.css')) .'" />' . "\n";

  return $head;
}

/**
 * Afficher agenda, messages et annonces sur la page d'accueil
 *
 * @param array $flux
 * @return array
 */
function organiseur_affiche_droite($flux){
	if ($flux['args']['exec']=='accueil'){
		$flux['data'] .= recuperer_fond(
			'prive/squelettes/inclure/organiseur-rappels',
			array(
				'id_auteur'=>$GLOBALS['visiteur_session']['id_auteur'],
				'last' => $GLOBALS['visiteur_session']['quand'],
			)
		);
	}
  return $flux;
}

/**
 * Afficher le formulaire de configuration sur la page concernee
 *
 * @param array $flux
 * @return array
 */
function organiseur_affiche_milieu($flux){
	if ($flux['args']['exec']=='configurer_interactions'){
		$c = recuperer_fond('prive/squelettes/inclure/configurer_messagerie',array());
	  if ($p = strpos($flux['data'],'<!--contenu_prive-->'))
		  $flux['data'] = substr_replace($flux['data'],$c,$p,0);
	  else
		  $flux['data'] .= $c;
	}
  return $flux;
}