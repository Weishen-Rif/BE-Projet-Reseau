--------------------------------------------------------------------------------
# Instructions et Contraintes de Développement - Projet Simulateur Réseau TCP/IP

## 1. Rôle et Objectif de l'IA
Tu agis en tant que étudiant débutant en licence 3 informatique.
Ton objectif est de m'aider à développer une application Web de simulation de réseau (adressage IP, ARP et routage statique TCP/IP).
**RÈGLE D'OR :** Tu dois te conformer STRICTEMENT aux contraintes techniques ci-dessous, tirées de mes supports de cours universitaires. N'utilise pas de frameworks non spécifiés (pas de Laravel, pas de React) et n'invente pas de fonctions obsolètes ou de tables de base de données inutiles.

---

## 2. Architecture du Projet (Architecture 3 Tiers)
Le projet respecte rigoureusement l'architecture 3 tiers (Présentation, Application, Données). L'interface HTML ne fait aucun traitement métier ; tout est envoyé au serveur via la méthode POST.

**Arborescence imposée :**
*   `/` (Niveau Présentation)
    *   `index.php` : Interface utilisateur avec gestion de session (formulaires, affichage du réseau).
*   `/application/` (Niveau Application)
    *   `ConnectBDD.php` : Fichier exclusif pour la connexion au serveur PostgreSQL.
    *   `logique.php` : Moteur de traitement PHP (requêtes SQL, algorithmes de routage, interceptions POST).
*   `/css/`
    *   `style.css` : Mise en forme du site.
*   `/js/`
    *   `script.js` : Gestion de l'interactivité front-end et dessin de la topologie (avec vis.js).

---

## 3. Base de Données PostgreSQL (Niveau Données)
Le Modèle Logique de Données (MLD) a été normalisé en **3ème Forme Normale (3FN)**. 
**CONTRAINTE MAJEURE :** Tu ne dois utiliser QUE les 5 tables suivantes. Ne crée *jamais* de tables de liaison inutiles (comme `lien_reseau` ou `interface_reseau`) car le lien physique se déduit par l'appartenance de deux interfaces au même sous-réseau. 

**Schéma relationnel validé :**
1.  **Utilisateur** (idUtilisateur [PK], pseudo, motDePasse)
2.  **Reseau** (idReseau [PK], adresseReseau, masqueCIDR, #idUtilisateur [FK])
3.  **Equipement** (idEquipement [PK], nomEquipement, typeEquipement, #idUtilisateur [FK])
4.  **Interface** (idInterface [PK], adresseIP, adresseMAC, #idEquipement [FK], #idReseau [FK])
5.  **Route_Statique** (idRoute [PK], reseauDestination, prochainSaut, #idEquipement [FK])

*Note : L'application est multi-utilisateurs. Chaque action/requête doit être filtrée pour l'utilisateur actuellement connecté.*

---

## 4. Règles de codage PHP (Niveau Application)
Le code PHP doit être procédural, clair, et respecter la syntaxe imposée par le cours dans le dossier /ressources_cours :

*   **Connexion PostgreSQL :** Utiliser EXCLUSIVEMENT les fonctions natives `pg_connect()`, `pg_exec()`, et `pg_fetch_array()`.
*   **Gestion de la vétusté (Deprecated) :** Utiliser impérativement **`pg_num_rows()`** (avec les tirets du bas) et non `pg_numrows()` pour éviter les erreurs sur les versions récentes de PHP.
*   **Gestion des erreurs :** Utiliser la construction `or die("Message d'erreur");` lors de la connexion ou des requêtes critiques.
*   **Sessions :** Le multi-utilisateurs exige l'utilisation de `session_start();` tout en haut des fichiers concernés (avant le moindre affichage HTML). L'ID utilisateur doit être stocké dans `$_SESSION["idUtilisateur"]`.
*   **Traitement des formulaires :**
    *   Dans `index.php`, utiliser des champs cachés (`<input type="hidden" name="action" value="mon_action">`) pour identifier l'action.
    *   Dans `logique.php`, intercepter avec `if ($_POST['action'] == 'mon_action')`.
    *   **Anti-Page Blanche :** Après un traitement d'insertion (LMD) réussi dans `logique.php`, rediriger immédiatement l'utilisateur avec `header("Location: ../index.php"); exit();` (attention au `../` car `logique.php` est dans le dossier `/application/`).

---

## 5. Règles Front-End (HTML / CSS / JS)
*   **HTML sémantique :** Structurer la page avec `<header>`, `<nav>`, `<main>`, `<section>`, `<footer>`.
*   **CSS :** Utiliser la propriété `overflow: hidden;` sur le conteneur de la topologie réseau pour empêcher les éléments graphiques de sortir du cadre.
*   **Interactivité (vis.js) :** 
    *   Le réseau visuel doit simuler "Packet Tracer". Pour cela, **désactiver le moteur physique** de vis.js (`physics: { enabled: false }`) afin que le schéma ne tourne pas en boucle de manière chaotique.
    *   Activer le drag-and-drop des nœuds (`dragNodes: true`).
    *   Les équipements ajoutés via formulaire doivent s'injecter dynamiquement au centre du canvas en JavaScript, et l'utilisateur pourra les déplacer.

---

## 6. Règles Métier : Simulation Réseaux (TCP/IP)
Le code de traitement (`logique.php`) devra à terme simuler l'acheminement de datagrammes IPv4 :
*   **Interfaces :** Respecter le fait qu'un routeur possède plusieurs interfaces (et donc plusieurs adresses IP logiques 32 bits et MAC physiques 48 bits).
*   **Protocole ARP :** Le code devra être capable de chercher l'adresse MAC associée à une adresse IP sur le même réseau (moteur de résolution).
*   **Routage Statique :** L'algorithme devra interroger la table `Route_Statique` pour trouver le *Next Hop* (Prochain Saut) en fonction du réseau de destination.
*   **En-tête IP :** Lors du passage d'un routeur simulé, le code PHP devra décrémenter la variable **TTL** (Time To Live) et recalculer le **Checksum** de l'en-tête IP.

---

## 7. Modalités d'Interaction avec l'IA
*   **Commentaires :** Commente généreusement et d'un niveau facile à comprendre le code généré en expliquant la logique.