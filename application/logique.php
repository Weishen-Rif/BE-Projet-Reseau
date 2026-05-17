<?php
// =========================================================================
// Projet Réseau - Fichier de Logique Métier (Backend)
// Responsable : Ayyub BOUTAHIR
// Rôle : Cœur du moteur réseau et logique métier
// C'est ici que l'on traite les formulaires et qu'on exécute les algorithmes réseaux.
// =========================================================================
include_once("ConnectBDD.php");

// =============================================================================
// FONCTIONS UTILITAIRES (Sécurité et calculs IP)
// =============================================================================

// Sécurisation basique des affichages (Les injections SQL sont gérées par PDO)
function nettoyer($valeur) {
    return trim(htmlspecialchars($valeur, ENT_QUOTES, 'UTF-8'));
}

// Validation du format IPv4
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

// Simulation du calcul du Checksum de l'en-tête IP (RFC 791)
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

// Extraction des informations d'interface pour éviter la redondance (DRY)
function getInterfaceInfo($idEq) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT i.adresseip, i.adressemac, i.idreseau, r.adressereseau, r.masquecidr, e.nomequipement 
        FROM Interface i JOIN Reseau r ON i.idreseau = r.idreseau 
        JOIN Equipement e ON i.idequipement = e.idequipement
        WHERE i.idequipement = ? LIMIT 1");
    $stmt->execute([$idEq]);
    return $stmt->fetch();
}

// Orchestration de la simulation complète : ALLER puis RETOUR
function tracerChemin($idSource, $idDest) {
    $aller = simulerUnSens($idSource, $idDest, 'ICMP Echo Request', 1);
    
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
    global $pdo;
    
    $chemin = [];
    
    $ifaceSource = getInterfaceInfo($idSource);
    
    if (!$ifaceSource) {
        return [['erreur' => 'L\'équipement source n\'a pas d\'interface configurée.']];
    }
    
    $ifaceDest = getInterfaceInfo($idDest);
    
    if (!$ifaceDest) {
        return [['erreur' => 'L\'équipement destination n\'a pas d\'interface configurée.']];
    }
    
    $ttl = 64; // Durée de vie initiale du paquet IP
    
    // Petite fonction pratique pour numéroter les étapes proprement
    $getEtape = function() use (&$chemin, $etapeDepart) {
        return $etapeDepart + count($chemin);
    };
    
    $chemin[] = [
        'etape' => $getEtape(),
        'equipement' => 'Source: ' . $ifaceSource['nomequipement'],
        'ip' => $ifaceSource['adresseip'],
        'reseau' => $ifaceSource['adressereseau'] . '/' . $ifaceSource['masquecidr'],
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
        $stmtDirect = $pdo->prepare("SELECT idreseau FROM Interface WHERE idequipement = ? AND idreseau = ? LIMIT 1");
        $stmtDirect->execute([$eqCourantId, $ifaceDest['idreseau']]);
        
        if ($rowDirect = $stmtDirect->fetch()) {
            $trouve = true;
            $netCourantId = $rowDirect['idreseau'];
            break; // Le routeur (ou PC) est directement connecté au bon réseau !
        }
        
        // RÈGLE 1 & 3 : Le routage est nécessaire. Quel est le prochain saut ?
        $prochainSaut = null;
        
        // On vérifie d'abord la table de routage statique (RÈGLE 3)
        $stmtRoute = $pdo->prepare("SELECT prochainsaut FROM Route_Statique 
                     WHERE idequipement = ? 
                     AND reseaudestination IN (?, ?, '0.0.0.0', '0.0.0.0/0')
                     ORDER BY reseaudestination DESC LIMIT 1");
        $stmtRoute->execute([$eqCourantId, $ifaceDest['adressereseau'], $ifaceDest['adressereseau'] . '/' . $ifaceDest['masquecidr']]);
        
        if ($route = $stmtRoute->fetch()) {
            $prochainSaut = $route['prochainsaut'];
        } else {
            // RÈGLE 1 : Si c'est un PC (Hôte), on utilise automatiquement le routeur de son réseau comme passerelle par défaut
            $stmtType = $pdo->prepare("SELECT typeequipement FROM Equipement WHERE idequipement = ?");
            $stmtType->execute([$eqCourantId]);
            $typeEq = ($rowType = $stmtType->fetch()) ? $rowType['typeequipement'] : 'Inconnu';
            
            if ($typeEq == 'Hote') {
                $stmtPasserelle = $pdo->prepare("SELECT i2.adresseip FROM Interface i1 JOIN Interface i2 ON i1.idreseau = i2.idreseau JOIN Equipement e2 ON i2.idequipement = e2.idequipement WHERE i1.idequipement = ? AND e2.typeequipement = 'Routeur' LIMIT 1");
                $stmtPasserelle->execute([$eqCourantId]);
                if ($rowPass = $stmtPasserelle->fetch()) {
                    $prochainSaut = $rowPass['adresseip'];
                }
            }
        }

        if ($prochainSaut) {
            // On cherche le routeur cible grâce à l'IP du prochain saut
            $stmtRouteur = $pdo->prepare("SELECT e.idequipement, e.nomequipement, i.adressemac FROM Interface i JOIN Equipement e ON i.idequipement = e.idequipement WHERE i.adresseip = ? LIMIT 1");
            $stmtRouteur->execute([$prochainSaut]);
            
            if ($routeur = $stmtRouteur->fetch()) {
                
                // On identifie le réseau qui relie les deux équipements pour l'animation graphique
                $stmtNet = $pdo->prepare("SELECT i1.idreseau FROM interface i1 JOIN interface i2 ON i1.idreseau = i2.idreseau WHERE i1.idequipement=? AND i2.idequipement=? LIMIT 1");
                $stmtNet->execute([$eqCourantId, $routeur['idequipement']]);
                $netCommun = ($rowNet = $stmtNet->fetch()) ? $rowNet['idreseau'] : $netCourantId;
                
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
            $stmtNom = $pdo->prepare("SELECT nomequipement FROM Equipement WHERE idequipement = ?");
            $stmtNom->execute([$eqCourantId]);
            $nomEq = ($rowNom = $stmtNom->fetch()) ? $rowNom['nomequipement'] : "ID $eqCourantId";
            
            $chemin[] = ['erreur' => "Erreur de routage : L'équipement '$nomEq' ne trouve aucune route (passerelle) vers la destination."];
            
            if ($eqCourantId != $idSource) {
                $stmtIpRouter = $pdo->prepare("SELECT adresseip FROM Interface WHERE idequipement = ? LIMIT 1");
                $stmtIpRouter->execute([$eqCourantId]);
                $ipRouter = ($rowIp = $stmtIpRouter->fetch()) ? $rowIp['adresseip'] : 'Inconnue';
                
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

if (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];

    switch ($action) {
        
        // --- AUTHENTIFICATION ---
        case 'connexion':
            $pseudo = nettoyer($_POST['pseudo']);
            $mdp = nettoyer($_POST['motDePasse']);
            
            $stmt = $pdo->prepare("SELECT idutilisateur, pseudo FROM utilisateur WHERE pseudo=? AND motdepasse=?");
            $stmt->execute([$pseudo, $mdp]);
            
            if ($user = $stmt->fetch()) {
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
            
            $stmt = $pdo->prepare("INSERT INTO reseau (adressereseau, masquecidr, idutilisateur) VALUES (?, ?, ?)");
            $stmt->execute([$adresseReseau, $masqueCIDR, $_SESSION['idUtilisateur']]);
            
            header("Location: ../index.php");
            exit();

        // --- GESTION DES EQUIPEMENTS ---
        case 'ajouter_equipement':
            $nomEquipement = nettoyer($_POST['nomEquipement']);
            $typeEquipement = nettoyer($_POST['typeEquipement']);
            
            if (!in_array($typeEquipement, ['Routeur', 'Hote'])) {
                die("Erreur : Type d'équipement invalide.");
            }
            
            $stmt = $pdo->prepare("INSERT INTO equipement (nomequipement, typeequipement, idutilisateur) VALUES (?, ?, ?)");
            $stmt->execute([$nomEquipement, $typeEquipement, $_SESSION['idUtilisateur']]);
            
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
            
            $stmtNet = $pdo->prepare("SELECT adressereseau, masquecidr FROM reseau WHERE idreseau=?");
            $stmtNet->execute([$idReseau]);
            $netDB = $stmtNet->fetch();
            
            if ($masqueInterface != $netDB['masquecidr']) {
                die("Erreur : Le masque saisi (/$masqueInterface) ne correspond pas au masque du réseau sélectionné (/" . $netDB['masquecidr'] . ").");
            }
            
            if (!ipDansReseau($adresseIP, $netDB['adressereseau'], $masqueInterface)) {
                die("Erreur : L'adresse IP $adresseIP n'appartient pas au réseau " . $netDB['adressereseau'] . "/$masqueInterface.");
            }
            
            if (!preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $adresseMAC)) {
                die("Erreur : Adresse MAC invalide (format attendu: XX:XX:XX:XX:XX:XX).");
            }
            
            $stmt = $pdo->prepare("INSERT INTO interface (adresseip, adressemac, idequipement, idreseau) VALUES (?, ?, ?, ?)");
            $stmt->execute([$adresseIP, $adresseMAC, $idEquipement, $idReseau]);
            
            header("Location: ../index.php");
            exit();

        // --- GESTION DES ROUTES STATIQUES ---
        case 'ajouter_route':
            $reseauDestination = nettoyer($_POST['reseauDestination']);
            $masqueCIDR = (int)$_POST['masqueCIDR'];
            $prochainSaut = nettoyer($_POST['prochainSaut']);
            $idEquipement = (int)$_POST['idEquipement'];
            
            $reseauPropre = calculerReseau($reseauDestination, $masqueCIDR);
            
            $stmt = $pdo->prepare("INSERT INTO route_statique (reseaudestination, prochainsaut, idequipement) VALUES (?, ?, ?)");
            $stmt->execute([$reseauPropre, $prochainSaut, $idEquipement]);
            
            header("Location: ../index.php");
            exit();

        // --- SUPPRESSION D'ELEMENTS ---
        case 'supprimer':
            $typeElement = nettoyer($_POST['typeElement']);
            $idElement = (int)$_POST['idElement'];
            $idU = (int)$_SESSION['idUtilisateur'];
            
            switch ($typeElement) {
                case 'equipement':
                    $stmt = $pdo->prepare("SELECT 1 FROM equipement WHERE idequipement = ? AND idutilisateur = ?");
                    $stmt->execute([$idElement, $idU]);
                    if ($stmt->fetch()) {
                        $pdo->prepare("DELETE FROM route_statique WHERE idequipement = ?")->execute([$idElement]);
                        $pdo->prepare("DELETE FROM interface WHERE idequipement = ?")->execute([$idElement]);
                        $pdo->prepare("DELETE FROM equipement WHERE idequipement = ?")->execute([$idElement]);
                    }
                    break;
                case 'reseau':
                    $stmt = $pdo->prepare("SELECT 1 FROM reseau WHERE idreseau = ? AND idutilisateur = ?");
                    $stmt->execute([$idElement, $idU]);
                    if ($stmt->fetch()) {
                        $pdo->prepare("DELETE FROM interface WHERE idreseau = ?")->execute([$idElement]);
                        $pdo->prepare("DELETE FROM reseau WHERE idreseau = ?")->execute([$idElement]);
                    }
                    break;
                case 'interface':
                    $stmt = $pdo->prepare("DELETE FROM interface WHERE idinterface = ? AND idequipement IN (SELECT idequipement FROM equipement WHERE idutilisateur = ?)");
                    $stmt->execute([$idElement, $idU]);
                    break;
                case 'route':
                    $stmt = $pdo->prepare("DELETE FROM route_statique WHERE idroute = ? AND idequipement IN (SELECT idequipement FROM equipement WHERE idutilisateur = ?)");
                    $stmt->execute([$idElement, $idU]);
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

            $stmtNet = $pdo->prepare("SELECT idreseau, adressereseau, masquecidr, x, y FROM reseau WHERE idutilisateur=?");
            $stmtNet->execute([$idU]);
            while ($row = $stmtNet->fetch()) {
                $nodes[] = [
                    'id' => 'net_' . $row['idreseau'],
                    'label' => $row['adressereseau'] . '/' . $row['masquecidr'],
                    'shape' => 'image',
                    'image' => 'images/reseauv2.png',
                    'size' => 70,
                    'font' => ['size' => 13, 'color' => '#000000', 'vadjust' => -80, 'strokeWidth' => 0, 'bold' => true],
                    'x' => $row['x'] !== null ? (float)$row['x'] : rand(-200, 200),
                    'y' => $row['y'] !== null ? (float)$row['y'] : rand(-200, 200)
                ];
            }

            $stmtEq = $pdo->prepare("SELECT idequipement, nomequipement, typeequipement, x, y FROM equipement WHERE idutilisateur=?");
            $stmtEq->execute([$idU]);
            while ($row = $stmtEq->fetch()) {
                $imagePath = ($row['typeequipement'] == 'Routeur') ? 'images/routeurv2.png' : 'images/hote.png';
                
                $nodes[] = [
                    'id' => 'eq_' . $row['idequipement'],
                    'label' => $row['nomequipement'] . "\n(" . $row['typeequipement'] . ")",
                    'shape' => 'image',
                    'image' => $imagePath,
                    'font' => ['size' => 12, 'color' => '#333', 'background' => 'rgba(255, 255, 255, 0.8)'],
                    'x' => $row['x'] !== null ? (float)$row['x'] : rand(-200, 200),
                    'y' => $row['y'] !== null ? (float)$row['y'] : rand(-200, 200)
                ];
            }

            $stmtInt = $pdo->prepare("SELECT i.idinterface, i.adresseip, i.idequipement, i.idreseau, e.nomequipement 
                                      FROM interface i 
                                      JOIN equipement e ON i.idequipement = e.idequipement 
                                      WHERE e.idutilisateur = ?");
            $stmtInt->execute([$idU]);
            while ($row = $stmtInt->fetch()) {
                $edges[] = [
                    'from' => 'eq_' . $row['idequipement'],
                    'to' => 'net_' . $row['idreseau'],
                    'label' => $row['adresseip'],
                    'font' => ['size' => 10, 'align' => 'middle'],
                    'color' => '#2c3e50'
                ];
            }

            $stmtRoutes = $pdo->prepare("SELECT r.idroute, r.reseaudestination, r.prochainsaut, e.idequipement, e.nomequipement 
                                         FROM route_statique r 
                                         JOIN equipement e ON r.idequipement = e.idequipement 
                                         WHERE e.idutilisateur = ?");
            $stmtRoutes->execute([$idU]);
            
            $routesGroupees = [];
            while ($row = $stmtRoutes->fetch()) {
                $idEq = $row['idequipement'];
                if (!isset($routesGroupees[$idEq])) {
                    $routesGroupees[$idEq] = [];
                }
                $routesGroupees[$idEq][] = '→ ' . $row['reseaudestination'] . ' via ' . $row['prochainsaut'];
            }
            
            foreach ($routesGroupees as $idEq => $listeRoutes) {
                $edges[] = [
                    'from' => 'eq_' . $idEq,
                    'to' => 'eq_' . $idEq,
                    'label' => implode("\n", $listeRoutes),
                    'dashes' => true,
                    'color' => '#9b59b6',
                    'font' => ['size' => 10, 'color' => '#9b59b6', 'align' => 'bottom']
                ];
            }

            echo json_encode(['nodes' => $nodes, 'edges' => $edges]);
            exit();

        // --- API POUR LES DÉTAILS (Quand on clique sur un équipement) ---
        case 'get_node_details':
            $nodeIdRaw = nettoyer($_POST['nodeId']);
            $idU = (int)$_SESSION['idUtilisateur'];
            
            $data = ['success' => false];
            
            if (strpos($nodeIdRaw, 'eq_') === 0) {
                $idEq = (int)str_replace('eq_', '', $nodeIdRaw);
                
                $stmtEq = $pdo->prepare("SELECT * FROM equipement WHERE idequipement=? AND idutilisateur=?");
                $stmtEq->execute([$idEq, $idU]);
                if ($row = $stmtEq->fetch()) {
                    $data['success'] = true;
                    $data['type'] = 'equipement';
                    $data['info'] = [
                        'id' => $row['idequipement'],
                        'nom' => $row['nomequipement'],
                        'typeEq' => $row['typeequipement']
                    ];
                    
                    $stmtInt = $pdo->prepare("SELECT i.idinterface, i.adresseip, i.adressemac, r.adressereseau, r.masquecidr 
                                              FROM interface i JOIN reseau r ON i.idreseau = r.idreseau 
                                              WHERE i.idequipement=?");
                    $stmtInt->execute([$idEq]);
                    $interfaces = [];
                    while ($intRow = $stmtInt->fetch()) {
                        $interfaces[] = [
                            'id' => $intRow['idinterface'],
                            'ip' => $intRow['adresseip'],
                            'mac' => $intRow['adressemac'],
                            'reseau' => $intRow['adressereseau'] . '/' . $intRow['masquecidr']
                        ];
                    }
                    $data['interfaces'] = $interfaces;
                    
                    $stmtRoute = $pdo->prepare("SELECT idroute, reseaudestination, prochainsaut FROM route_statique WHERE idequipement=?");
                    $stmtRoute->execute([$idEq]);
                    $routes = [];
                    while ($routeRow = $stmtRoute->fetch()) {
                        $routes[] = [
                            'id' => $routeRow['idroute'],
                            'dest' => $routeRow['reseaudestination'], 
                            'nextHop' => $routeRow['prochainsaut']
                        ];
                    }
                    $data['routes'] = $routes;
                }
            } elseif (strpos($nodeIdRaw, 'net_') === 0) {
                $idNet = (int)str_replace('net_', '', $nodeIdRaw);
                
                $stmtNet = $pdo->prepare("SELECT * FROM reseau WHERE idreseau=? AND idutilisateur=?");
                $stmtNet->execute([$idNet, $idU]);
                if ($row = $stmtNet->fetch()) {
                    $data['success'] = true;
                    $data['type'] = 'reseau';
                    $data['info'] = ['id' => $row['idreseau'], 'reseau' => $row['adressereseau'] . '/' . $row['masquecidr']];
                    
                    $stmtInt = $pdo->prepare("SELECT i.idinterface, i.adresseip, i.adressemac, e.nomequipement, e.typeequipement 
                                              FROM interface i JOIN equipement e ON i.idequipement = e.idequipement WHERE i.idreseau=?");
                    $stmtInt->execute([$idNet]);
                    $interfaces = [];
                    while ($intRow = $stmtInt->fetch()) {
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

// =============================================================================
// MODÈLE DE DONNÉES POUR LA VUE (Séparation MVC)
// =============================================================================

function getEquipementsUtilisateur($idU) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT idequipement, nomequipement, typeequipement FROM Equipement WHERE idutilisateur=?");
    $stmt->execute([$idU]);
    return $stmt->fetchAll();
}

function getReseauxUtilisateur($idU) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT idreseau, adressereseau, masquecidr FROM Reseau WHERE idutilisateur=?");
    $stmt->execute([$idU]);
    return $stmt->fetchAll();
}

// Protection contre l'exécution directe si ce fichier est inclus comme modèle
if (basename($_SERVER['PHP_SELF']) === 'logique.php') {
    header("Location: ../index.php");
    exit();
}
?>