## Simulateur Réseau TCP/IP

Bienvenue sur le dépôt du **Simulateur Réseau TCP/IP**. Ce projet est une application Web interactive permettant de modéliser des topologies réseau, de configurer l'adressage IP et le routage statique, et de simuler visuellement l'acheminement de datagrammes à travers le réseau (façon *Cisco Packet Tracer*).

Ce projet a été réalisé dans le cadre d'un cursus universitaire (Licence 3 IRT).

- Tester l'application en ligne : [Insérez le lien du site]
- Consulter le dépôt GitHub : https://github.com/Weishen-Rif/BE-Projet-Reseau

---

## Fonctionnalités Principales

- **Multi-utilisateurs :** Chaque étudiant/utilisateur possède son propre espace de travail cloisonné.
- **Création de Topologie :** Ajout de réseaux (nuages), d'hôtes (PC) et de routeurs.
- **Configuration IP & MAC :** Attribution d'adresses IPv4, de masques CIDR et d'adresses MAC aux interfaces.
- **Routage Statique :** Configuration des tables de routage (passerelles) pour les hôtes et les routeurs.
- **Simulation de Ping (ICMP) :** 
  - Résolution d'adresses physiques via le protocole **ARP** (Broadcast/Unicast).
  - Acheminement saut-par-saut avec décrémentation du **TTL** et recalcul du **Checksum IP**.
  - Gestion des erreurs de routage avec retours **ICMP Destination Unreachable**.
- **Visualisation en Temps Réel :** Topologie interactive générée avec **Vis.js** et animation du parcours des paquets à l'écran.

---

## Architecture du Projet

Le projet respecte strictement une architecture logicielle en 3 Tiers, sans utilisation de frameworks lourds pour la logique backend, afin de démontrer la maîtrise des concepts fondamentaux :

1. **Couche Présentation (Front-end)**
   - `index.php` : Interface utilisateur (HTML5, formulaires).
   - `css/style.css` : Mise en page complète (Flexbox, design "Terminal" pour la console).
   - `js/script.js` : Interactivité, requêtes AJAX (Fetch), génération de la topologie Vis.js et moteur d'animation 2D.

2. **Couche Application (Logique Métier)**
   - `application/logique.php` : Cœur du projet (PHP Procédural). Intercepte les requêtes POST, exécute les requêtes SQL, valide les adresses IP et orchestre l'algorithme complet de routage ("Packet Tracer Like").

3. **Couche Données (Back-end)**
   - `application/ConnectBDD.php` : Connexion native à la base de données PostgreSQL.
   - **PostgreSQL** : Base de données normalisée en 3FN (Tables : `Utilisateur`, `Reseau`, `Equipement`, `Interface`, `Route_Statique`).

---

## Prérequis et Installation

### 1. Prérequis
- Serveur Web (ex: Laragon, XAMPP, WAMP) avec **PHP 7.4 ou supérieur**.
- SGBD **PostgreSQL**.
- L'extension PDO/PgSQL activée dans votre fichier `php.ini`.

### 2. Installation
1. Clonez ce dépôt dans le répertoire web de votre serveur local (ex: `C:\laragon\www\projet_reseau_gemini`).
2. Créez une base de données PostgreSQL nommée `simulation_reseau`.
3. Importez le schéma SQL (modèle de données) dans cette base.
4. Importez `donnees_test.sql` pour charger directement notre jeu de données d'évaluation (incluant un compte préconfiguré et une topologie complète à 3 routeurs et 3 hôtes prêts à l'emploi)
.
5. Ouvrez le fichier `application/ConnectBDD.php` et vérifiez que les identifiants de connexion correspondent à votre installation locale :
   ```php
   $host = "localhost";
   $port = "5432";
   $dbname = "simulation_reseau";
   $user = "postgres";
   $password = "votre_mot_de_passe"; 
   ```
6. Accédez au projet via votre navigateur à l'adresse : `http://localhost/projet_reseau_gemini`

---

## Mode d'emploi rapide





> **⚠️ Note à l'évaluateur :** Les étapes ci-dessous sont destinées au cas où vous avez démarré avec un espace de travail vierge (via `base_de_donnees.sql`). Si vous avez importé le fichier `donnees_test.sql` lors de l'installation, cette topologie est déjà entièrement construite et routée. Vous pouvez alors vous connecter et passer directement à l'étape 6 "Simulation" !

1. **Connexion** : Connectez-vous avec vos identifiants. (Par défaut : `admin` | `admin123`).
2. **Créer des réseaux** : Dans la barre d'outils, créez trois réseaux distincts pour permettre un routage multi-sauts : un réseau source (`192.168.3.0/24`), un réseau de transit (`192.168.2.0/24`) et un réseau de destination (`192.168.5.0/24`).
3. **Ajouter le matériel** : Ajoutez deux Hôtes (PC 1, PC 2) et deux Routeurs (Routeur 1, Routeur 2).
4. **Câbler (Interfaces)** : 
   - Connectez PC 1 au réseau source (ex: `192.168.3.1`).
   - Connectez PC 2 au réseau de destination (ex: `192.168.5.1`).
   - Connectez le Routeur 1 au réseau source (interface `192.168.3.254`) et au réseau de transit (interface `192.168.2.253`).
   - Connectez le Routeur 2 au réseau de transit (interface `192.168.2.252`) et au réseau de destination (interface `192.168.5.254`).
5. **Routage** : 
   - **Sur les PC :** Ajoutez une route par défaut (`0.0.0.0/0`) sur PC 1 pointant vers sa passerelle `192.168.3.254`, et une sur PC 2 pointant vers `192.168.5.254`.
   - **Sur les Routeurs :** Sur le Routeur 1, ajoutez une route vers le réseau `192.168.5.0/24` via le prochain saut `192.168.2.252` (Routeur 2). Sur le Routeur 2, ajoutez une route vers le réseau `192.168.3.0/24` via le prochain saut `192.168.2.253` (Routeur 1).
6. **Simulation** : Dans la console à droite, lancez un datagramme IP de PC 1 vers PC 2. Vous pourrez observer la cinématique complète : les résolutions ARP, la double décrémentation du TTL et le recalcul du Checksum IP à chaque passage de routeur !

---

*Développé par Abasse ALI, Robin RIGAL et Ayyub BOUTAHIR.*
