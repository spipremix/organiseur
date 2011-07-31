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
 * Installation/maj des tables messagerie
 *
 * @param string $nom_meta_base_version
 * @param string $version_cible
 */
function organiseur_upgrade($nom_meta_base_version,$version_cible){
	// cas particulier :
	// si plugin pas installe mais que la table existe
	// considerer que c'est un upgrade depuis v 1.0.0
	// pour gerer l'historique des installations SPIP <=2.1
	if (!isset($GLOBALS['meta'][$nom_meta_base_version])){
		$trouver_table = charger_fonction('trouver_table','base');
		if ($desc = $trouver_table('spip_messages')
		  AND isset($desc['exist'])){
			ecrire_meta($nom_meta_base_version,'1.0.0');
		}
		// si pas de table en base, on fera une simple creation de base
	}
	
	$maj = array();
	$maj['create'] = array(
		array('maj_tables',array('spip_messages')),
		array('sql_alter','TABLE spip_auteurs ADD imessage VARCHAR(3)'),
		array('sql_alter','TABLE spip_auteurs ADD messagerie VARCHAR(3)'),
	);

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}


/**
 * Desinstallation/suppression des tables mots et groupes de mots
 *
 * @param string $nom_meta_base_version
 */
function organiseur_vider_tables($nom_meta_base_version) {
	sql_drop_table("spip_messages");
	sql_alter("TABLE spip_auteurs DROP imessage");
	sql_alter("TABLE spip_auteurs DROP messagerie");

	effacer_meta('messagerie_agenda');

	effacer_meta($nom_meta_base_version);
}



/**
  * Reunir en une seule table les liens de mots dans spip_mots_liens
  * Passe spip_mots_xx(id_mot, id_xx) dans spip_mots_liens(objet, id_objet, id_mot)
  * (peut fonctionner pour d'autres table spip_xx_liens).
  *
  * @param array $objets : liste d'objets à transférer.
  * @param string $destination : table de destination (se terminant par _liens).
  * @param bool $supprimer_ancienne_table : supprimer l'ancienne table une fois la copie réalisée ?.
  * @return 
 **/ 
function organiseur_maj_tables_liaisons ($objets, $destination='spip_mots_liens', $supprimer_ancienne_table = true) {
	// creer la table spip_mots_liens manquante
	include_spip('base/create');
	creer_base();

	$trouver_table = charger_fonction('trouver_table','base');
	
	// Recopier les donnees
	foreach ($objets as $objet) {
		$table_objet = table_objet($objet);
		if ($table_objet == 'forums') $table_objet = 'forum'; // #naze #bug #forum
		$_id_objet = id_table_objet($objet);
		$source = substr($destination, 0, -5) . $table_objet; // spip_mots_xx
		spip_log("Transfert SQL de : '$source' vers '$destination'");
		
		if (!$trouver_table($source)) continue; // la source n'existe pas... ne rien tenter...
		
		if ($s = sql_select('*', $source)) {
			$tampon = array();
			while ($t = sql_fetch($s)) {
				// transformer id_xx=N en (id_objet=N, objet=xx)
				$t['id_objet'] = $t[$_id_objet];
				$t['objet'] = $objet;
				unset($t[$_id_objet]);
				unset($t['maj']);
				$tampon[] = $t;
				if (count($tampon)>10000) {
					sql_insertq_multi($destination, $tampon);
					$tampon = array();
				}
			}
			
			if (count($tampon)) {
				sql_insertq_multi($destination, $tampon);
			}
			
			if ($supprimer_ancienne_table) {
				sql_drop_table($source);
			}
		}
	}
}


?>
