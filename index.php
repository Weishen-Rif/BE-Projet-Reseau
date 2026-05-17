<?php
// =========================================================================
// Projet Réseau - Interface Utilisateur (Vue principale)
// Couche Présentation de l'architecture 3 tiers. Ne contient aucun traitement métier.
// =========================================================================
include_once("application/ConnectBDD.php");
$estConnecte = isset($_SESSION['idUtilisateur']);
$simulationActive = isset($_SESSION['simulation']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Simulateur Réseau TCP/IP</title>
    <link rel="stylesheet" href="css/style.css">
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
</head>
<body>

    <div id="global-container">

        <?php if (!$estConnecte): ?>
            <!-- Écran de connexion si l'utilisateur n'est pas encore identifié -->
            <div id="login-section">
                <div class="login-logos">
                    <img src="images/Logo_upssitech.png" alt="Logo Upssitech" class="logo-ecole">
                    <img src="images/UnivTlse2025.png" alt="Logo Université" class="logo-univ">
                </div>
                <h1>Simulation Réseau TCP/IP</h1>
                <form action="application/logique.php" method="POST">
                    <input type="hidden" name="action" value="connexion">
                    <div class="form-group">
                        <label>Pseudo :</label>
                        <input type="text" name="pseudo" required>
                    </div>
                    <div class="form-group">
                        <label>Mot de passe :</label>
                        <input type="password" name="motDePasse" required>
                    </div>
                    <button type="submit">Entrer dans la simulation</button>
                </form>
            </div>

        <?php else: ?>
            <!-- En-tête de l'application une fois connecté -->
            <header>
                <div class="header-left">
                    <img src="images/Logo_upssitech.png" alt="Logo" class="mini-logo">
                    <img src="images/UnivTlse2025.png" alt="Logo" class="mini-logo">
                    <span>Connecté : <strong><?php echo isset($_SESSION['pseudo']) ? $_SESSION['pseudo'] : 'Admin'; ?></strong></span>
                </div>
                <form action="application/logique.php" method="POST">
                    <input type="hidden" name="action" value="deconnexion">
                    <button type="submit" class="btn-danger">Déconnexion</button>
                </form>
            </header>

            <!-- Optimisation : On pré-charge les données de la BDD une seule fois ici -->
            <!-- Cela évite de refaire des requêtes SQL identiques pour chaque sous-menu -->
            <?php
            $idU = $_SESSION['idUtilisateur'];
            
            $resEq = pg_exec($connect, "SELECT idequipement, nomequipement, typeequipement FROM Equipement WHERE idutilisateur=$idU");
            $equipements = []; $hotes = [];
            while($row = pg_fetch_array($resEq)) { 
                $equipements[] = $row; 
                if ($row['typeequipement'] == 'Hote') $hotes[] = $row;
            }
            
            $resNet = pg_exec($connect, "SELECT idreseau, adressereseau, masquecidr FROM Reseau WHERE idutilisateur=$idU");
            $reseaux = [];
            while($row = pg_fetch_array($resNet)) { $reseaux[] = $row; }
            ?>

            <!-- Barre d'outils principale (Boutons d'action) -->
            <div class="top-toolbars">
                <div class="main-toolbar">
                    <button class="tb-btn" onclick="toggleToolbar('tb-reseau')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"></path></svg>
                        Créer un réseau
                    </button>
                    <button class="tb-btn" onclick="toggleToolbar('tb-equipement')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="20" x2="22" y2="20"></line></svg>
                        Ajouter un équipement
                    </button>
                    <button class="tb-btn" onclick="toggleToolbar('tb-interface')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22v-5"/><path d="M9 8V2"/><path d="M15 8V2"/><path d="M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8Z"/></svg>
                        Configurer une interface
                    </button>
                    <button class="tb-btn" onclick="toggleToolbar('tb-route')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 8 16 13"></polyline><line x1="21" y1="8" x2="9" y2="8"></line><polyline points="8 21 3 16 8 11"></polyline><line x1="3" y1="16" x2="15" y2="16"></line></svg>
                        Configurer une route statique
                    </button>
                    <div style="flex-grow:1;"></div> <!-- Espaceur -->
                    <button style="color:white;" class="tb-btn btn-danger" onclick="toggleToolbar('tb-supprimer')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        Supprimer
                    </button>
                </div>

                <!-- Formulaires cachés (Ils s'affichent en cliquant sur la barre d'outils) -->
                <div id="tb-reseau" class="sub-toolbar hidden">
                    <form action="application/logique.php" method="POST" class="inline-form">
                        <input type="hidden" name="action" value="ajouter_reseau">
                        <label>Adresse :</label> <input type="text" name="adresseReseau" placeholder="Ex: 192.168.1.0" required>
                        <label>Masque CIDR :</label> <input type="number" name="masqueCIDR" placeholder="24" min="0" max="32" required style="width: 80px;">
                        <button type="submit">Ajouter</button>
                    </form>
                </div>

                <div id="tb-equipement" class="sub-toolbar hidden">
                    <form action="application/logique.php" method="POST" class="inline-form">
                        <input type="hidden" name="action" value="ajouter_equipement">
                        <label>Nom :</label> <input type="text" name="nomEquipement" placeholder="Nom (ex: R1, PC1)" required>
                        <label>Type :</label>
                        <select name="typeEquipement">
                            <option value="Routeur">Routeur</option>
                            <option value="Hote">Hôte</option>
                        </select>
                        <button type="submit">Créer</button>
                    </form>
                </div>

                <div id="tb-interface" class="sub-toolbar hidden">
                    <form action="application/logique.php" method="POST" class="inline-form">
                        <input type="hidden" name="action" value="ajouter_interface">
                        <label>IP / Masque :</label>
                        <input type="text" name="adresseIP" placeholder="Ex: 192.168.1.1" required style="width: 140px;">
                        <input type="number" name="masqueInterface" placeholder="24" min="0" max="32" required style="width: 70px;">
                        <label>MAC :</label> <input type="text" name="adresseMAC" placeholder="AA:BB..." required style="width: 130px;">
                        <label>Équipement :</label>
                        <select name="idEquipement">
                            <?php foreach($equipements as $eq) echo "<option value='".$eq['idequipement']."'>".$eq['nomequipement']."</option>"; ?>
                        </select>
                        <label>Réseau :</label>
                        <select name="idReseau">
                            <?php foreach($reseaux as $net) echo "<option value='".$net['idreseau']."'>".$net['adressereseau']."/".$net['masquecidr']."</option>"; ?>
                        </select>
                        <button type="submit">Connecter</button>
                    </form>
                </div>

                <div id="tb-route" class="sub-toolbar hidden">
                    <form action="application/logique.php" method="POST" class="inline-form">
                        <input type="hidden" name="action" value="ajouter_route">
                        <label>Dest. / Masque :</label>
                        <input type="text" name="reseauDestination" placeholder="192.168.2.0" required style="width: 140px;">
                        <input type="number" name="masqueCIDR" placeholder="24" min="0" max="32" required style="width: 70px;">
                        <label>Passerelle :</label> <input type="text" name="prochainSaut" placeholder="192.168.1.254" required style="width: 140px;">
                        <label>Équipement :</label>
                        <select name="idEquipement">
                            <?php foreach($equipements as $eq) echo "<option value='".$eq['idequipement']."'>".$eq['nomequipement']."</option>"; ?>
                        </select>
                        <button type="submit">Ajouter Route</button>
                    </form>
                </div>

                <div id="tb-supprimer" class="sub-toolbar hidden">
                    <form action="application/logique.php" method="POST" class="inline-form">
                        <input type="hidden" name="action" value="supprimer">
                        <label>Élément :</label>
                        <select name="typeElement">
                            <option value="equipement">Équipement</option>
                            <option value="reseau">Réseau</option>
                            <option value="interface">Interface</option>
                            <option value="route">Route</option>
                        </select>
                        <label>ID :</label> <input type="number" name="idElement" placeholder="ID" required style="width: 100px;">
                        <button type="submit" class="btn-danger">Supprimer définitivement</button>
                    </form>
                </div>
            </div>

            <main style="position: relative;">
                <!-- Zone de dessin pour la topologie réseau (Vis.js) -->
                <div id="mynetwork"></div>

                <!-- Panneau d'informations qui apparaît quand on clique sur un équipement -->
                <div id="node-details-panel" class="hidden">
                    <div class="panel-header">
                        <h3 id="nd-title">Détails</h3>
                        <button type="button" id="nd-close" class="btn-danger" style="padding: 2px 8px; width:auto; font-size: 0.9em;">X</button>
                    </div>
                    <div id="nd-content"></div>
                </div>

                <!-- Console de droite : Lancement et suivi de la simulation IP -->
                <aside id="console-panel">
                    <div class="console-header">
                        <h3> Simuler Datagramme IP</h3>
                        <form action="application/logique.php" method="POST">
                            <input type="hidden" name="action" value="simuler_datagramme">
                            <div style="display:flex; gap:10px; margin-bottom:10px;">
                                <select name="idSource" required title="Source">
                                    <option value="" disabled selected>De (Hôte)...</option>
                                    <?php foreach($hotes as $hote) echo "<option value='".$hote['idequipement']."'>".$hote['nomequipement']."</option>"; ?>
                                </select>
                                <select name="idDestination" required title="Destination">
                                    <option value="" disabled selected>Vers (Hôte)...</option>
                                    <?php foreach($hotes as $hote) echo "<option value='".$hote['idequipement']."'>".$hote['nomequipement']."</option>"; ?>
                                </select>
                            </div>
                            <?php if ($simulationActive): ?>
                                <button type="button" class="btn-simulation" disabled style="background-color: #bdc3c7; cursor: not-allowed;" title="Veuillez fermer la fenêtre de résultats de la simulation en cours pour en lancer une nouvelle.">Simulation en cours...</button>
                            <?php else: ?>
                                <button type="submit" class="btn-simulation" onclick="this.innerText='Calcul en cours...';">Lancer Simulation</button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Zone d'affichage des étapes (façon Terminal) -->
                    <div class="console-results" id="console-results">
                        <?php if ($simulationActive): ?>
                            <?php 
                            // On récupère les résultats calculés par logique.php
                            $simulation = $_SESSION['simulation'];
                            // On vide la session pour que la page redevienne propre au prochain F5
                            unset($_SESSION['simulation']); 
                            if (is_array($simulation) && isset($simulation['erreur'])) $simulation = [$simulation];
                            ?>
                            <form action="application/logique.php" method="POST" style="margin-bottom: 15px;">
                                <input type="hidden" name="action" value="effacer_simulation">
                                <button type="submit" class="btn-danger" style="width:100%;">Fermer la simulation</button>
                            </form>
                            <ul id="simulation-list">
                                <?php foreach ($simulation as $etape): ?>
                                    <?php if (is_array($etape)): ?>
                                        <!-- Affichage spécifique si c'est une erreur de routage -->
                                        <?php if (isset($etape['erreur'])): ?>
                                            <li class="sim-step-item erreur">❌ <?php echo htmlspecialchars($etape['erreur']); ?></li>
                                        <?php else: ?>
                                            <li class="sim-step-item">
                                                <strong>Étape <?php echo htmlspecialchars((string)($etape['etape'] ?? '?')); ?> - <?php echo htmlspecialchars((string)($etape['equipement'] ?? 'Inconnu')); ?></strong><br>
                                                Action: <?php echo htmlspecialchars((string)($etape['action'] ?? '')); ?><br>
                                                IP: <?php echo htmlspecialchars((string)($etape['ip'] ?? '')); ?> (Réseau: <?php echo htmlspecialchars((string)($etape['reseau'] ?? '')); ?>)<br>
                                                <em>TTL: <?php echo htmlspecialchars((string)($etape['ttl'] ?? '')); ?> | Checksum IP: 0x<?php echo htmlspecialchars((string)($etape['checksum'] ?? '')); ?></em>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                            <!-- On transmet discrètement les données au JavaScript pour lancer l'animation visuelle -->
                            <script>window.simulationData = <?php echo json_encode($simulation); ?>;</script>
                        <?php else: ?>
                            <div style="color:#7f8c8d; text-align:center; margin-top:50px; line-height: 1.5;">
                                <em>> En attente d'une simulation...</em><br>
                                Utilisez le panneau ci-dessus pour lancer un datagramme IP.
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </main>
            <script src="js/script.js"></script>
        <?php endif; ?>
    </div>
</body>
</html>