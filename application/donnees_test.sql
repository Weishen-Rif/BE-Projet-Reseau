-- ==============================================================================
-- JEU DE DONNÉES DE TEST - SIMULATEUR RÉSEAU TCP/IP
-- ==============================================================================
-- Ce script précharge un environnement complet pour tester le simulateur 
-- instantanément, sans devoir configurer le réseau de zéro.
--
-- À noter : Ces données ont été créées organiquement via l'interface Web 
-- de l'application, puis exportées automatiquement avec pgAdmin.
--
-- Ce fichier contient :
-- 1. Un compte utilisateur préconfiguré.
-- 2. Une topologie prête à l'emploi (4 réseaux, 3 routeurs, PC Robin, PC Meud et PC Ayyub).
--
-- Utilisation : Importez ce script dans votre base de données, connectez-vous, 
-- et lancez directement un "Ping" pour voir l'acheminement en action !
--
-- Développé par : Abasse ALI, Robin RIGAL et Ayyub BOUTAHIR.
-- ==============================================================================

-- 1. Insertion de l'utilisateur
INSERT INTO public.utilisateur (idutilisateur, pseudo, motdepasse) 
VALUES 
(1, 'admin', 'admin123');

-- 2. Insertion des équipements
INSERT INTO public.equipement (idequipement, nomequipement, typeequipement, idutilisateur, x, y) 
VALUES 
(11, 'PC Meud', 'Hote', 1, 380, 207),
(5, 'Routeur 1', 'Routeur', 1, -624, 21),
(10, 'Routeur 2', 'Routeur', 1, -325, -231),
(7, 'PC Ayyub', 'Hote', 1, -873, -273),
(9, 'PC Robin', 'Hote', 1, 10, -438),
(13, 'Routeur 3', 'Routeur', 1, 59, 31);

-- 3. Insertion des réseaux
INSERT INTO public.reseau (idreseau, adressereseau, masquecidr, idutilisateur, x, y) 
VALUES 
(8, '192.168.5.0', 24, 1, 379, 8),
(5, '192.168.2.0', 24, 1, -333, 29),
(2, '192.168.1.0', 24, 1, -618, -255),
(6, '192.168.3.0', 24, 1, 11, -220);

-- 4. Insertion des interfaces réseau
INSERT INTO public.interface (idinterface, adresseip, adressemac, idequipement, idreseau) 
VALUES 
(7, '192.168.1.1', '00:30:84:9d:e4:c5', 7, 2),
(8, '192.168.1.254', '00:00:0c:06:13:4a', 5, 2),
(9, '192.168.2.254', '00:50:ba:c5:6a:2a', 5, 5),
(11, '192.168.2.253', '00:00:0c:06:13:8a', 10, 5),
(12, '192.168.3.254', '00:00:0c:06:13:3a', 10, 6),
(13, '192.168.3.1', '00:00:0c:06:18:3a', 9, 6),
(14, '192.168.5.1', '02:60:9c:2e:b5:6b', 11, 8),
(16, '192.168.5.254', '00:00:0c:06:20:8a', 13, 8),
(17, '192.168.2.252', '02:64:8c:2e:b5:8b', 13, 5);

-- 5. Insertion des tables de routage statiques
INSERT INTO public.route_statique (idroute, reseaudestination, prochainsaut, idequipement) 
VALUES 
(7, '0.0.0.0', '192.168.1.254', 7),
(9, '0.0.0.0', '192.168.3.254', 9),
(10, '192.168.3.0', '192.168.2.253', 5),
(11, '192.168.1.0', '192.168.2.254', 10),
(12, '192.168.5.0', '192.168.2.252', 10),
(13, '192.168.3.0', '192.168.2.253', 13),
(14, '0.0.0.0', '192.168.5.254', 11),
(16, '0.0.0.0', '192.168.2.252', 5),
(17, '0.0.0.0', '192.168.2.254', 13);


-- ============================================================================
-- 6. Mise à jour des séquences (Pour que les prochains ajouts auto-incrémentent bien)
-- ============================================================================
SELECT pg_catalog.setval('public.equipement_idequipement_seq', 16, true);
SELECT pg_catalog.setval('public.interface_idinterface_seq', 23, true);
SELECT pg_catalog.setval('public.interface_reseau_idinterface_seq', 1, false);
SELECT pg_catalog.setval('public.lien_reseau_idlien_seq', 1, false);
SELECT pg_catalog.setval('public.reseau_idreseau_seq', 10, true);
SELECT pg_catalog.setval('public.route_statique_idroute_seq', 17, true);
SELECT pg_catalog.setval('public.utilisateur_idutilisateur_seq', 1, true);
