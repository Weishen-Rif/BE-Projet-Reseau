<?php
// =========================================================================
// Projet Réseau - Fichier de Logique Métier (Backend)
// C'est ici que l'on traite les formulaires et qu'on exécute les algorithmes réseaux.
// =========================================================================
include_once("ConnectBDD.php");

// =============================================================================
// FONCTIONS UTILITAIRES (Sécurité et calculs IP)
// =============================================================================

// On sécurise systématiquement les saisies utilisateur pour éviter les injections SQL
function nettoyer($valeur) {
    global $connect;
    return pg_escape_string($connect, trim($valeur));
}

// On vérifie que l'IP saisie ressemble bien à une vraie adresse IPv4
function validerIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

// On calcule l'adresse du réseau en appliquant le masque CIDR sur l'IP
function calculerReseau($ip, $cidr) {
    // On passe par du binaire pour éviter les bugs liés aux serveurs 32/64 bits
    $bin = sprintf("%032b", ip2long($ip));
    $networkBin = substr($bin, 0, $cidr) . str_repeat("0", 32 - $cidr);
    return long2ip(bindec($networkBin));
}

// On vérifie si une machine fait bien partie d'un réseau spécifique
function ipDansReseau($ip, $reseau, $cidr) {
    return calculerReseau($ip, $cidr) === $reseau;
}

// On simule le calcul du Checksum de l'en-tête IP (comme le ferait un vrai routeur)
function calculerChecksum($source, $dest, $ttl) {
    $sum = 0;
    
    $sum += 0x4500; // Version (4) + IHL (5) + TOS (0)
    $sum += 0x0028; // Total Length (40 bytes)
    $sum += 0x1234; // Identification
    $sum += 0x0000; // Flags + Fragment Offset
    $sum += ($ttl << 8) + 6; // TTL + Protocol (ICMP simulé en TCP=6 pour l'exemple mathématique)
    
    $partsSource = explode('.', $source);
    $partsDest = explode('.', $dest);
    $sum += ($partsSource[0] << 8) + $partsSource[1];
    $sum += ($partsSource[2] << 8) + $partsSource[3];
    $sum += ($partsDest[0] << 8) + $partsDest[1];
    $sum += ($partsDest[2] << 8) + $partsDest[3];
    
    while ($sum > 0xFFFF) {
        $sum = ($sum & 0xFFFF) + ($sum >> 16);
    }
    
    // On retourne le complément à 1 (format hexadécimal)
    return sprintf("%04x", ~$sum & 0xFFFF);
}

// =============================================================================
// MOTEUR DE SIMULATION IP (L'algorithme principal du projet)
// =============================================================================

// On orchestre la simulation complète : d'abord l'ALLER, puis le RETOUR.
function tracerChemin($idSource, $idDest) {
    $aller = simulerUnSens($idSource, $idDest, 'ICMP Echo Request', 1);
    
    // S'il y a une erreur sur l'aller (ex: route manquante), on s'arrête là
    $hasError = false;
    foreach ($aller as $etape) {
        if (isset($etape['erreur'])) {
            $hasError = true;
            break;
        }
    }
    
    if ($hasError) {
        return $aller;
    }
    
    // Si le ping est bien arrivé, on simule le trajet retour (Echo Reply)
    $etapeRetour = count($aller) + 1;
    $retour = simulerUnSens($idDest, $idSource, 'ICMP Echo Reply', $etapeRetour);
    
    return array_merge($aller, $retour);
}

// On simule un trajet dans un seul sens (saut par saut)
function simulerUnSens($idSource, $idDest, $typeMessage, $etapeDepart) {
    global $connect;
    
    $chemin = [];
    
    // 1. On récupère les infos de l'interface de départ
    $sql = "SELECT i.adresseip, i.adressemac, i.idreseau, r.adressereseau, r.masquecidr, e.nomequipement 
            FROM Interface i 
            JOIN Reseau r ON i.idreseau = r.idreseau 
            JOIN Equipement e ON i.idequipement = e.idequipement
            WHERE i.idequipement = $idSource LIMIT 1";
    $res = pg_exec($connect, $sql) or die("Erreur récupération interface source.");
    $ifaceSource = pg_fetch_array($res);
    
    if (!$ifaceSource) {
        return [['erreur' => 'L\'équipement source n\'a pas d\'interface configurée.']];
    }
    
    // 2. On récupère les infos de l'interface d'arrivée
    $sql = "SELECT i.adresseip, i.adressemac, i.idreseau, r.adressereseau, r.masquecidr, e.nomequipement 
            FROM Interface i 
            JOIN Reseau r ON i.idreseau = r.idreseau 
            JOIN Equipement e ON i.idequipement = e.idequipement
            WHERE i.idequipement = $idDest LIMIT 1";
    $res = pg_exec($connect, $sql) or die("Erreur récupération interface destination.");
    $ifaceDest = pg_fetch_array($res);
    
    if (!$ifaceDest) {
        return [['erreur' => 'L\'équipement destination n\'a pas d\'interface configurée.']];
    }
    
    $ttl = 64; // Durée de vie initiale du paquet IP
    
    // Petite fonction pratique pour numéroter les étapes proprement
    $getEtape = function() use (&$chemin, $etapeDepart) {
        return $etapeDepart + count($chemin);
    };
    
    // ÉTAPE INITIALE : Création du paquet par la source
    $chemin[] = [
        'etape' => $getEtape(),
        'equipement' => 'Source: ' . $ifaceSource['nomequipement'],
        'ip' => $ifaceSource['adresseip'],
        'reseau' => $ifaceSource['adressereseau'] . '/' . $ifaceSource['masquecidr'],
        'action' => "Préparation du datagramme IP ($typeMessage)",
        'action' => "Préparation du datagramme IP ($typeMessage). Création de l'en-tête avec un TTL initial de $ttl et calcul du Checksum.",
        'ttl' => $ttl,
        'checksum' => calculerChecksum($ifaceSource['adresseip'], $ifaceDest['adresseip'], $ttl),
        'node_id' => 'eq_' . $idSource
    ];
    
    if ($ifaceSource['idreseau'] == $ifaceDest['idreseau']) {
        // CAS A : La destination est sur le MÊME réseau (Résolution ARP locale directe)
        $chemin[] = [
            'etape' => $getEtape(),
            'equipement' => 'Réseau Local (Commutateur)',
            'ip' => 'Broadcast',
            'reseau' => 'Trame Ethernet',
            'action' => "Requête ARP (Broadcast) : « Qui a l'IP " . $ifaceDest['adresseip'] . " ? » -> Cible MAC : FF:FF:FF:FF:FF:FF",
            'ttl' => '-',
            'checksum' => '-',
            'node_id' => 'net_' . $ifaceSource['idreseau']
        ];
        
        $chemin[] = [
            'etape' => $getEtape(),
            'equipement' => $ifaceDest['nomequipement'],
            'ip' => $ifaceDest['adresseip'],
            'reseau' => 'Trame Ethernet',
            'action' => "Réponse ARP (Unicast) : « Mon IP est " . $ifaceDest['adresseip'] . " et ma MAC est " . $ifaceDest['adressemac'] . " » -> Cache ARP de " . $ifaceSource['nomequipement'] . " mis à jour.",
            'ttl' => '-',
            'checksum' => '-',
            'node_id' => 'eq_' . $idDest
        ];
        
        $chemin[] = [
            'etape' => $getEtape(),
            'equipement' => 'Destination: ' . $ifaceDest['nomequipement'],
            'ip' => $ifaceDest['adresseip'],
            'reseau' => $ifaceDest['adressereseau'] . '/' . $ifaceDest['masquecidr'],
            'action' => "Réception du datagramme IP ($typeMessage)",
            'action' => "Réception du datagramme IP ($typeMessage). Le PC lit le TTL ($ttl) sans le modifier et valide le Checksum.",
            'ttl' => $ttl,
            'checksum' => calculerChecksum($ifaceSource['adresseip'], $ifaceDest['adresseip'], $ttl),
            'node_id' => 'eq_' . $idDest
        ];
        return $chemin;
    }
    
    // CAS B : La destination est sur un AUTRE réseau (On doit faire du routage)
    $maxSauts = 10;
    $sautActuel = 0;
    $eqCourantId = $idSource;
    $netCourantId = $ifaceSource['idreseau'];
    $trouve = false;

    while ($sautActuel < $maxSauts) {
        $sautActuel++;
        
        // RÈGLE 2 : Les réseaux directement connectés
        // Est-ce que l'équipement courant est physiquement branché au réseau de destination ?
        $sqlDirect = "SELECT idreseau FROM Interface WHERE idequipement = $eqCourantId AND idreseau = " . $ifaceDest['idreseau'] . " LIMIT 1";
        $resDirect = pg_exec($connect, $sqlDirect);
        
        if (pg_num_rows($resDirect) > 0) {
            $trouve = true;
            $netCourantId = pg_fetch_array($resDirect)['idreseau'];
            break; // Le routeur (ou PC) est directement connecté au bon réseau !
        }
        
        // RÈGLE 1 & 3 : Le routage est nécessaire. Quel est le prochain saut ?
        $prochainSaut = null;
        
        // On vérifie d'abord la table de routage statique (RÈGLE 3)
        $sqlRoute = "SELECT prochainsaut FROM Route_Statique 
                     WHERE idequipement = $eqCourantId 
                     AND (reseaudestination = '" . $ifaceDest['adressereseau'] . "' 
                       OR reseaudestination = '" . $ifaceDest['adressereseau'] . "/" . $ifaceDest['masquecidr'] . "'
                       OR reseaudestination = '0.0.0.0' 
                       OR reseaudestination = '0.0.0.0/0')
                     ORDER BY reseaudestination DESC LIMIT 1";
        $resRoute = pg_exec($connect, $sqlRoute);
        
        if (pg_num_rows($resRoute) > 0) {
            $route = pg_fetch_array($resRoute);
            $prochainSaut = $route['prochainsaut'];
        } else {
            // RÈGLE 1 : Si c'est un PC (Hôte), on utilise automatiquement le routeur de son réseau comme passerelle par défaut
            $resType = pg_exec($connect, "SELECT typeequipement FROM Equipement WHERE idequipement = $eqCourantId");
            $typeEq = pg_num_rows($resType) > 0 ? pg_fetch_array($resType)['typeequipement'] : 'Inconnu';
            
            if ($typeEq == 'Hote') {
                $sqlPasserelle = "SELECT i2.adresseip 
                                  FROM Interface i1 
                                  JOIN Interface i2 ON i1.idreseau = i2.idreseau 
                                  JOIN Equipement e2 ON i2.idequipement = e2.idequipement 
                                  WHERE i1.idequipement = $eqCourantId AND e2.typeequipement = 'Routeur' LIMIT 1";
                $resPasserelle = pg_exec($connect, $sqlPasserelle);
                if (pg_num_rows($resPasserelle) > 0) {
                    $prochainSaut = pg_fetch_array($resPasserelle)['adresseip'];
                }
            }
        }

        if ($prochainSaut) {
            // On cherche le routeur cible grâce à l'IP du prochain saut
            $sqlRouteur = "SELECT e.idequipement, e.nomequipement, i.adressemac 
                           FROM Interface i 
                           JOIN Equipement e ON i.idequipement = e.idequipement 
                           WHERE i.adresseip = '$prochainSaut' LIMIT 1";
            $resRouteur = pg_exec($connect, $sqlRouteur);
            
            if (pg_num_rows($resRouteur) > 0) {
                $routeur = pg_fetch_array($resRouteur);
                
                // On identifie le réseau qui relie les deux équipements pour l'animation graphique
                $sqlNet = "SELECT i1.idreseau FROM interface i1 JOIN interface i2 ON i1.idreseau = i2.idreseau WHERE i1.idequipement=$eqCourantId AND i2.idequipement=".$routeur['idequipement']." LIMIT 1";
                $resNet = pg_exec($connect, $sqlNet);
                $netCommun = pg_num_rows($resNet) > 0 ? pg_fetch_array($resNet)['idreseau'] : $netCourantId;
                
                $chemin[] = [
                    'etape' => $getEtape(),
                    'equipement' => 'Réseau Local',
                    'ip' => 'Broadcast',
                    'reseau' => 'Trame Ethernet',
                    'action' => "Requête ARP (Broadcast) : « Qui a l'IP $prochainSaut ? » -> Cible MAC : FF:FF:FF:FF:FF:FF",
                    'ttl' => '-',
                    'checksum' => '-',
                    'node_id' => 'net_' . $netCommun
                ];
                
                $chemin[] = [
                    'etape' => $getEtape(),
                    'equipement' => 'Passerelle: ' . $routeur['nomequipement'],
                    'ip' => $prochainSaut,
                    'reseau' => 'Trame Ethernet',
                    'action' => "Réponse ARP (Unicast) : « Ma MAC est " . $routeur['adressemac'] . " » -> Cache ARP mis à jour.",
                    'ttl' => '-',
                    'checksum' => '-',
                    'node_id' => 'eq_' . $routeur['idequipement']
                ];
                
                // LE ROUTEUR RÉDUIT LE TTL ET RECALCULE LE CHECKSUM
                $ttl--;
                if ($ttl <= 0) {
                    $chemin[] = ['erreur' => "Datagramme détruit par le routeur " . $routeur['nomequipement'] . " : TTL expiré (atteint 0)."];
                    return $chemin;
                }

                $chemin[] = [
                    'etape' => $getEtape(),
                    'equipement' => 'Routeur: ' . $routeur['nomequipement'],
                    'ip' => $prochainSaut,
                    'reseau' => 'Passerelle',
                    'action' => 'Routage - TTL décrémenté, Checksum recalculé',
                    'action' => "Routage - TTL décrémenté à $ttl, l'ancien Checksum est invalidé. Recalcul du nouveau Checksum IP avant transmission.",
                    'ttl' => $ttl,
                    'checksum' => calculerChecksum($ifaceSource['adresseip'], $ifaceDest['adresseip'], $ttl),
                    'node_id' => 'eq_' . $routeur['idequipement']
                ];
                
                // On avance au saut suivant (le routeur qu'on vient d'atteindre)
                $eqCourantId = $routeur['idequipement'];
            } else {
                return array_merge($chemin, [['erreur' => "Erreur ARP: Impossible de joindre le prochain saut ($prochainSaut)."]]);
            }
        } else {
            // ERREUR CRITIQUE : Le routeur (ou le PC) ne sait pas où envoyer le paquet !
            $resNom = pg_exec($connect, "SELECT nomequipement FROM Equipement WHERE idequipement = $eqCourantId");
            $nomEq = pg_num_rows($resNom) > 0 ? pg_fetch_array($resNom)['nomequipement'] : "ID $eqCourantId";
            
            $chemin[] = ['erreur' => "Erreur de routage : L'équipement '$nomEq' ne trouve aucune route (passerelle) vers la destination."];
            
            // Si c'est un routeur qui bloque, il renvoie gentiment un message d'erreur ICMP à la source
            if ($eqCourantId != $idSource) {
                $resIpRouter = pg_exec($connect, "SELECT adresseip FROM Interface WHERE idequipement = $eqCourantId LIMIT 1");
                $ipRouter = pg_num_rows($resIpRouter) > 0 ? pg_fetch_array($resIpRouter)['adresseip'] : 'Inconnue';
                
                $chemin[] = [
                    'etape' => $getEtape(),
                    'equipement' => "Routeur: $nomEq",
                    'ip' => $ipRouter,
                    'reseau' => 'ICMP Error',
                    'action' => "Génération d'un message d'erreur ICMP. Conformément au protocole, le $nomEq prépare un message ICMP de Type 3 (Destination inaccessible) avec le Code 0 (Réseau inaccessible) pour expliquer la raison de la non-délivrance.",
                    'ttl' => '-',
                    'checksum' => '-',
                    'node_id' => 'eq_' . $eqCourantId
                ];
                
                $chemin[] = [
                    'etape' => $getEtape(),
                    'equipement' => "Routeur: $nomEq",
                    'ip' => $ipRouter,
                    'reseau' => 'ICMP Error',
                    'action' => "Envoi du datagramme d'erreur vers la source. Le $nomEq encapsule ce message ICMP dans un nouveau datagramme IP. IP Source: $ipRouter | IP Destination: {$ifaceSource['adresseip']} (Trame envoyée en Unicast grâce au cache ARP de l'aller).",
                    'ttl' => 64,
                    'checksum' => calculerChecksum($ipRouter, $ifaceSource['adresseip'], 64),
                    'node_id' => 'eq_' . $eqCourantId
                ];
                
                $chemin[] = [
                    'etape' => $getEtape(),
                    'equipement' => 'Source: ' . $ifaceSource['nomequipement'],
                    'ip' => $ifaceSource['adresseip'],
                    'reseau' => 'Terminal',
                    'action' => "Traitement de l'erreur. {$ifaceSource['nomequipement']} reçoit le datagramme du routeur, lit le message ICMP et l'interface de commande affiche le message d'échec : « Réponse de $ipRouter : Impossible de joindre le réseau de destination ».",
                    'ttl' => '-',
                    'checksum' => '-',
                    'node_id' => 'eq_' . $idSource
                ];
            }
            
            return $chemin;
        }
    }
    
    if ($trouve) {
        $chemin[] = [
            'etape' => $getEtape(),
            'equipement' => 'Dernier saut local',
            'ip' => 'Broadcast',
            'reseau' => 'Trame Ethernet',
            'action' => "Requête ARP (Broadcast) : « Qui a l'IP " . $ifaceDest['adresseip'] . " ? » -> Cible MAC : FF:FF:FF:FF:FF:FF",
            'ttl' => '-',
            'checksum' => '-',
            'node_id' => 'net_' . $netCourantId
        ];
        
        $chemin[] = [
            'etape' => $getEtape(),
            'equipement' => $ifaceDest['nomequipement'],
            'ip' => $ifaceDest['adresseip'],
            'reseau' => 'Trame Ethernet',
            'action' => "Réponse ARP (Unicast) : « Ma MAC est " . $ifaceDest['adressemac'] . " » -> Cache ARP mis à jour.",
            'ttl' => '-',
            'checksum' => '-',
            'node_id' => 'eq_' . $idDest
        ];
        
        $chemin[] = [
            'etape' => $getEtape(),
            'equipement' => 'Destination: ' . $ifaceDest['nomequipement'],
            'ip' => $ifaceDest['adresseip'],
            'reseau' => $ifaceDest['adressereseau'] . '/' . $ifaceDest['masquecidr'],
            'action' => "Paquet délivré avec succès ($typeMessage)",
            'action' => "Paquet délivré avec succès ($typeMessage). L'hôte cible accepte le paquet, vérifie le Checksum et conserve le TTL tel quel ($ttl).",
            'ttl' => $ttl,
            'checksum' => calculerChecksum($ifaceSource['adresseip'], $ifaceDest['adresseip'], $ttl),
            'node_id' => 'eq_' . $idDest
        ];
    } else {
        $chemin[] = ['erreur' => "Datagramme abandonné: TTL expiré ou destination inatteignable après $maxSauts sauts."];
    }
    
    return $chemin;
}

// =============================================================================
// AIGUILLAGE DES FORMULAIRES (Méthodes POST)
// =============================================================================

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        
        // --- AUTHENTIFICATION ---
        case 'connexion':
            $pseudo = nettoyer($_POST['pseudo']);
            $mdp = nettoyer($_POST['motDePasse']);
            
            // Requête SQL avec protection contre les injections
            $sql = "SELECT idutilisateur, pseudo FROM utilisateur WHERE pseudo='$pseudo' AND motdepasse='$mdp'";
            $resultat = pg_exec($connect, $sql) or die("Erreur de connexion.");
            
            if (pg_num_rows($resultat) == 1) {
                $user = pg_fetch_array($resultat);
                $_SESSION['idUtilisateur'] = $user['idutilisateur'];
                $_SESSION['pseudo'] = $user['pseudo'];
            }
            header("Location: ../index.php");
            exit();

        case 'deconnexion':
            session_destroy();
            header("Location: ../index.php");
            exit();

        // --- GESTION DES RESEAUX ---
        case 'ajouter_reseau':
            $adresseReseau = nettoyer($_POST['adresseReseau']);
            $masqueCIDR = (int)$_POST['masqueCIDR'];
            
            if ($masqueCIDR < 1 || $masqueCIDR > 32) {
                die("Erreur : Le masque CIDR doit être entre 1 et 32.");
            }
            
            // Validation basique de l'adresse IP
            $parties = explode('.', $adresseReseau);
            if (count($parties) != 4) {
                die("Erreur : Adresse réseau invalide.");
            }
            
            $sql = "INSERT INTO reseau (adressereseau, masquecidr, idutilisateur) 
                    VALUES ('$adresseReseau', $masqueCIDR, " . $_SESSION['idUtilisateur'] . ")";
            pg_exec($connect, $sql) or die("Erreur lors de l'insertion du réseau.");
            
            header("Location: ../index.php");
            exit();

        // --- GESTION DES EQUIPEMENTS ---
        case 'ajouter_equipement':
            $nomEquipement = nettoyer($_POST['nomEquipement']);
            $typeEquipement = nettoyer($_POST['typeEquipement']);
            
            if (!in_array($typeEquipement, ['Routeur', 'Hote'])) {
                die("Erreur : Type d'équipement invalide.");
            }
            
            $sql = "INSERT INTO equipement (nomequipement, typeequipement, idutilisateur) 
                    VALUES ('$nomEquipement', '$typeEquipement', " . $_SESSION['idUtilisateur'] . ")";
            pg_exec($connect, $sql) or die("Erreur lors de l'insertion de l'équipement.");
            
            header("Location: ../index.php");
            exit();

        // --- GESTION DES INTERFACES ---
        case 'ajouter_interface':
            $adresseIP = nettoyer($_POST['adresseIP']);
            $masqueInterface = (int)$_POST['masqueInterface'];
            $adresseMAC = nettoyer($_POST['adresseMAC']);
            $idEquipement = (int)$_POST['idEquipement'];
            $idReseau = (int)$_POST['idReseau'];
            
            if (!validerIP($adresseIP)) {
                die("Erreur : Adresse IP invalide.");
            }
            
            $resNet = pg_exec($connect, "SELECT adressereseau, masquecidr FROM reseau WHERE idreseau=$idReseau");
            $netDB = pg_fetch_array($resNet);
            
            if ($masqueInterface != $netDB['masquecidr']) {
                die("Erreur : Le masque saisi (/$masqueInterface) ne correspond pas au masque du réseau sélectionné (/" . $netDB['masquecidr'] . ").");
            }
            
            if (!ipDansReseau($adresseIP, $netDB['adressereseau'], $masqueInterface)) {
                die("Erreur : L'adresse IP $adresseIP n'appartient pas au réseau " . $netDB['adressereseau'] . "/$masqueInterface.");
            }
            
            if (!preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $adresseMAC)) {
                die("Erreur : Adresse MAC invalide (format attendu: XX:XX:XX:XX:XX:XX).");
            }
            
            $sql = "INSERT INTO interface (adresseip, adressemac, idequipement, idreseau) 
                    VALUES ('$adresseIP', '$adresseMAC', $idEquipement, $idReseau)";
            pg_exec($connect, $sql) or die("Erreur lors de l'insertion de l'interface.");
            
            header("Location: ../index.php");
            exit();

        // --- GESTION DES ROUTES STATIQUES ---
        case 'ajouter_route':
            $reseauDestination = nettoyer($_POST['reseauDestination']);
            $masqueCIDR = (int)$_POST['masqueCIDR'];
            $prochainSaut = nettoyer($_POST['prochainSaut']);
            $idEquipement = (int)$_POST['idEquipement'];
            
            // On force l'adresse du réseau à être propre (ex: si on tape 192.168.2.10/24, on enregistre 192.168.2.0)
            $reseauPropre = calculerReseau($reseauDestination, $masqueCIDR);
            
            $sql = "INSERT INTO route_statique (reseaudestination, prochainsaut, idequipement) 
                    VALUES ('$reseauPropre', '$prochainSaut', $idEquipement)";
            pg_exec($connect, $sql) or die("Erreur lors de l'insertion de la route.");
            
            header("Location: ../index.php");
            exit();

        // --- SUPPRESSION D'ELEMENTS ---
        case 'supprimer':
            $typeElement = nettoyer($_POST['typeElement']);
            $idElement = (int)$_POST['idElement'];
            $idU = (int)$_SESSION['idUtilisateur'];
            
            // On gère la suppression en cascade manuellement pour respecter les clés étrangères de la BDD
            switch ($typeElement) {
                case 'equipement':
                    // On vérifie que l'équipement appartient bien à l'utilisateur
                    $check = pg_exec($connect, "SELECT 1 FROM equipement WHERE idequipement = $idElement AND idutilisateur = $idU");
                    if (pg_num_rows($check) > 0) {
                        // On nettoie d'abord les dépendances (Routes puis Interfaces)
                        pg_exec($connect, "DELETE FROM route_statique WHERE idequipement = $idElement");
                        pg_exec($connect, "DELETE FROM interface WHERE idequipement = $idElement");
                        // Puis on supprime l'équipement sereinement
                        pg_exec($connect, "DELETE FROM equipement WHERE idequipement = $idElement");
                    }
                    break;
                case 'reseau':
                    $check = pg_exec($connect, "SELECT 1 FROM reseau WHERE idreseau = $idElement AND idutilisateur = $idU");
                    if (pg_num_rows($check) > 0) {
                        // On débranche les interfaces associées à ce réseau
                        pg_exec($connect, "DELETE FROM interface WHERE idreseau = $idElement");
                        pg_exec($connect, "DELETE FROM reseau WHERE idreseau = $idElement");
                    }
                    break;
                case 'interface':
                    pg_exec($connect, "DELETE FROM interface WHERE idinterface = $idElement AND idequipement IN (SELECT idequipement FROM equipement WHERE idutilisateur = $idU)");
                    break;
                case 'route':
                    pg_exec($connect, "DELETE FROM route_statique WHERE idroute = $idElement AND idequipement IN (SELECT idequipement FROM equipement WHERE idutilisateur = $idU)");
                    break;
                default:
                    die("Erreur : Type d'élément invalide.");
            }
            
            header("Location: ../index.php");
            exit();

        // --- SIMULATION DU PAQUET IP ---
        case 'simuler_datagramme':
            $idSource = (int)$_POST['idSource'];
            $idDestination = (int)$_POST['idDestination'];
            
            if ($idSource == $idDestination) {
                die("Erreur : La source et la destination ne peuvent pas être identiques.");
            }
            
            // On trace le chemin et on sauvegarde le résultat en session pour l'afficher sur l'index
            $chemin = tracerChemin($idSource, $idDestination);
            $_SESSION['simulation'] = $chemin;
            
            header("Location: ../index.php");
            exit();

        case 'effacer_simulation':
            unset($_SESSION['simulation']);
            header("Location: ../index.php");
            exit();

        // --- API POUR LE DESSIN DU RÉSEAU (Appelé par JavaScript en AJAX) ---
        case 'get_topology':
            $idU = $_SESSION['idUtilisateur'];
            $nodes = [];
            $edges = [];

            // 1. Les nuages de réseau
            $resReseaux = pg_exec($connect, "SELECT idreseau, adressereseau, masquecidr FROM reseau WHERE idutilisateur=$idU");
            while ($row = pg_fetch_array($resReseaux)) {
                $nodes[] = [
                    'id' => 'net_' . $row['idreseau'],
                    'label' => $row['adressereseau'] . '/' . $row['masquecidr'],
                    'shape' => 'image',
                    'image' => 'images/reseauv2.png',
                    'size' => 70,
                    'font' => ['size' => 13, 'color' => '#000000', 'vadjust' => -80, 'strokeWidth' => 0, 'bold' => true],
                    'x' => rand(-200, 200),
                    'y' => rand(-200, 200)
                ];
            }

            // 2. Les équipements matériels (Routeurs et PC)
            $resEq = pg_exec($connect, "SELECT idequipement, nomequipement, typeequipement FROM equipement WHERE idutilisateur=$idU");
            while ($row = pg_fetch_array($resEq)) {
                $imagePath = ($row['typeequipement'] == 'Routeur') ? 'images/routeurv2.png' : 'images/hote.png';
                
                $nodes[] = [
                    'id' => 'eq_' . $row['idequipement'],
                    'label' => $row['nomequipement'] . "\n(" . $row['typeequipement'] . ")",
                    'shape' => 'image',
                    'image' => $imagePath,
                    'font' => ['size' => 12, 'color' => '#333', 'background' => 'rgba(255, 255, 255, 0.8)'],
                    'x' => rand(-200, 200),
                    'y' => rand(-200, 200)
                ];
            }

            // 3. Les câbles (Liens physiques entre équipements et réseaux)
            $resInt = pg_exec($connect, "SELECT i.idinterface, i.adresseip, i.idequipement, i.idreseau, e.nomequipement 
                                          FROM interface i 
                                          JOIN equipement e ON i.idequipement = e.idequipement 
                                          WHERE e.idutilisateur = $idU");
            while ($row = pg_fetch_array($resInt)) {
                $edges[] = [
                    'from' => 'eq_' . $row['idequipement'],
                    'to' => 'net_' . $row['idreseau'],
                    'label' => $row['adresseip'],
                    'font' => ['size' => 10, 'align' => 'middle'],
                    'color' => '#2c3e50'
                ];
            }

            // 4. Représentation visuelle des routes statiques (Flèches pointillées)
            $resRoutes = pg_exec($connect, "SELECT r.idroute, r.reseaudestination, r.prochainsaut, e.idequipement, e.nomequipement 
                                            FROM route_statique r 
                                            JOIN equipement e ON r.idequipement = e.idequipement 
                                            WHERE e.idutilisateur = $idU");
            
            // On regroupe d'abord les routes par équipement pour éviter qu'elles ne se superposent visuellement
            $routesGroupees = [];
            while ($row = pg_fetch_array($resRoutes)) {
                $idEq = $row['idequipement'];
                if (!isset($routesGroupees[$idEq])) {
                    $routesGroupees[$idEq] = [];
                }
                $routesGroupees[$idEq][] = '→ ' . $row['reseaudestination'] . ' via ' . $row['prochainsaut'];
            }
            
            // Ensuite on crée une seule flèche par équipement avec les textes mis à la ligne
            foreach ($routesGroupees as $idEq => $listeRoutes) {
                $edges[] = [
                    'from' => 'eq_' . $idEq,
                    'to' => 'eq_' . $idEq,
                    'label' => implode("\n", $listeRoutes), // On fusionne les routes avec un saut de ligne
                    'dashes' => true,
                    'color' => '#9b59b6',
                    'font' => ['size' => 10, 'color' => '#9b59b6', 'align' => 'bottom']
                ];
            }

            echo json_encode(['nodes' => $nodes, 'edges' => $edges]);
            exit();

        // --- API POUR LES DÉTAILS (Quand on clique sur un équipement) ---
        case 'get_node_details':
            $nodeIdRaw = nettoyer($_POST['nodeId']); // ex: eq_1, net_2
            $idU = (int)$_SESSION['idUtilisateur'];
            
            $data = ['success' => false];
            
            // Si on a cliqué sur un équipement
            if (strpos($nodeIdRaw, 'eq_') === 0) {
                $idEq = (int)str_replace('eq_', '', $nodeIdRaw);
                
                $resEq = pg_exec($connect, "SELECT * FROM equipement WHERE idequipement=$idEq AND idutilisateur=$idU");
                if ($row = pg_fetch_array($resEq)) {
                    $data['success'] = true;
                    $data['type'] = 'equipement';
                    $data['info'] = [
                        'id' => $row['idequipement'],
                        'nom' => $row['nomequipement'],
                        'typeEq' => $row['typeequipement']
                    ];
                    
                    $resInt = pg_exec($connect, "SELECT i.idinterface, i.adresseip, i.adressemac, r.adressereseau, r.masquecidr 
                                                 FROM interface i 
                                                 JOIN reseau r ON i.idreseau = r.idreseau 
                                                 WHERE i.idequipement=$idEq");
                    $interfaces = [];
                    while ($intRow = pg_fetch_array($resInt)) {
                        $interfaces[] = [
                            'id' => $intRow['idinterface'],
                            'ip' => $intRow['adresseip'],
                            'mac' => $intRow['adressemac'],
                            'reseau' => $intRow['adressereseau'] . '/' . $intRow['masquecidr']
                        ];
                    }
                    $data['interfaces'] = $interfaces;
                    
                    $resRoute = pg_exec($connect, "SELECT idroute, reseaudestination, prochainsaut FROM route_statique WHERE idequipement=$idEq");
                    $routes = [];
                    while ($routeRow = pg_fetch_array($resRoute)) {
                        $routes[] = [
                            'id' => $routeRow['idroute'],
                            'dest' => $routeRow['reseaudestination'], 
                            'nextHop' => $routeRow['prochainsaut']
                        ];
                    }
                    $data['routes'] = $routes;
                }
            // Si on a cliqué sur un nuage de réseau
            } elseif (strpos($nodeIdRaw, 'net_') === 0) {
                $idNet = (int)str_replace('net_', '', $nodeIdRaw);
                
                $resNet = pg_exec($connect, "SELECT * FROM reseau WHERE idreseau=$idNet AND idutilisateur=$idU");
                if ($row = pg_fetch_array($resNet)) {
                    $data['success'] = true;
                    $data['type'] = 'reseau';
                    $data['info'] = ['id' => $row['idreseau'], 'reseau' => $row['adressereseau'] . '/' . $row['masquecidr']];
                    
                    $resInt = pg_exec($connect, "SELECT i.idinterface, i.adresseip, i.adressemac, e.nomequipement, e.typeequipement 
                                                 FROM interface i JOIN equipement e ON i.idequipement = e.idequipement WHERE i.idreseau=$idNet");
                    $interfaces = [];
                    while ($intRow = pg_fetch_array($resInt)) {
                        $interfaces[] = [
                            'id' => $intRow['idinterface'],
                            'equipement' => $intRow['nomequipement'] . ' (' . $intRow['typeequipement'] . ')', 
                            'ip' => $intRow['adresseip'], 
                            'mac' => $intRow['adressemac']
                        ];
                    }
                    $data['interfaces'] = $interfaces;
                }
            }
            
            header('Content-Type: application/json');
            echo json_encode($data);
            exit();
    }
}

// Redirection par défaut si aucune action reconnue
header("Location: ../index.php");
exit();