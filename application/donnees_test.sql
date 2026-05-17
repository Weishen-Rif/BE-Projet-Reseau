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

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 224 (class 1259 OID 25892)
-- Name: equipement; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.equipement (
    idequipement integer NOT NULL,
    nomequipement character varying(50) NOT NULL,
    typeequipement character varying(50) NOT NULL,
    idutilisateur integer NOT NULL,
    x double precision,
    y double precision
);


ALTER TABLE public.equipement OWNER TO postgres;

--
-- TOC entry 223 (class 1259 OID 25891)
-- Name: equipement_idequipement_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.equipement_idequipement_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.equipement_idequipement_seq OWNER TO postgres;

--
-- TOC entry 4991 (class 0 OID 0)
-- Dependencies: 223
-- Name: equipement_idequipement_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.equipement_idequipement_seq OWNED BY public.equipement.idequipement;


--
-- TOC entry 226 (class 1259 OID 25908)
-- Name: interface; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.interface (
    idinterface integer NOT NULL,
    adresseip character varying(15) NOT NULL,
    adressemac character varying(17) NOT NULL,
    idequipement integer NOT NULL,
    idreseau integer NOT NULL
);


ALTER TABLE public.interface OWNER TO postgres;

--
-- TOC entry 225 (class 1259 OID 25907)
-- Name: interface_idinterface_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.interface_idinterface_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.interface_idinterface_seq OWNER TO postgres;

--
-- TOC entry 4992 (class 0 OID 0)
-- Dependencies: 225
-- Name: interface_idinterface_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.interface_idinterface_seq OWNED BY public.interface.idinterface;


--
-- TOC entry 230 (class 1259 OID 25946)
-- Name: interface_reseau; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.interface_reseau (
    idinterface integer NOT NULL,
    idequipement integer NOT NULL,
    nominterface character varying(50) NOT NULL,
    adresseip character varying(50),
    adressemac character varying(50),
    idsousreseau character varying(100),
    masque character varying(50),
    passerelle character varying(50)
);


ALTER TABLE public.interface_reseau OWNER TO postgres;

--
-- TOC entry 229 (class 1259 OID 25945)
-- Name: interface_reseau_idinterface_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.interface_reseau_idinterface_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.interface_reseau_idinterface_seq OWNER TO postgres;

--
-- TOC entry 4993 (class 0 OID 0)
-- Dependencies: 229
-- Name: interface_reseau_idinterface_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.interface_reseau_idinterface_seq OWNED BY public.interface_reseau.idinterface;


--
-- TOC entry 232 (class 1259 OID 25961)
-- Name: lien_reseau; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.lien_reseau (
    idlien integer NOT NULL,
    idequipementsource integer NOT NULL,
    idequipementdestination integer NOT NULL,
    interfacesource character varying(50) NOT NULL,
    interfacedestination character varying(50) NOT NULL,
    typelien character varying(20) DEFAULT 'Cable'::character varying NOT NULL
);


ALTER TABLE public.lien_reseau OWNER TO postgres;

--
-- TOC entry 231 (class 1259 OID 25960)
-- Name: lien_reseau_idlien_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.lien_reseau_idlien_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.lien_reseau_idlien_seq OWNER TO postgres;

--
-- TOC entry 4994 (class 0 OID 0)
-- Dependencies: 231
-- Name: lien_reseau_idlien_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.lien_reseau_idlien_seq OWNED BY public.lien_reseau.idlien;


--
-- TOC entry 233 (class 1259 OID 25984)
-- Name: position_equipement; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.position_equipement (
    idequipement integer NOT NULL,
    posx double precision NOT NULL,
    posy double precision NOT NULL
);


ALTER TABLE public.position_equipement OWNER TO postgres;

--
-- TOC entry 222 (class 1259 OID 25876)
-- Name: reseau; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.reseau (
    idreseau integer NOT NULL,
    adressereseau character varying(15) NOT NULL,
    masquecidr integer NOT NULL,
    idutilisateur integer NOT NULL,
    x double precision,
    y double precision
);


ALTER TABLE public.reseau OWNER TO postgres;

--
-- TOC entry 221 (class 1259 OID 25875)
-- Name: reseau_idreseau_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.reseau_idreseau_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.reseau_idreseau_seq OWNER TO postgres;

--
-- TOC entry 4995 (class 0 OID 0)
-- Dependencies: 221
-- Name: reseau_idreseau_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.reseau_idreseau_seq OWNED BY public.reseau.idreseau;


--
-- TOC entry 228 (class 1259 OID 25930)
-- Name: route_statique; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.route_statique (
    idroute integer NOT NULL,
    reseaudestination character varying(20) NOT NULL,
    prochainsaut character varying(15) NOT NULL,
    idequipement integer NOT NULL
);


ALTER TABLE public.route_statique OWNER TO postgres;

--
-- TOC entry 227 (class 1259 OID 25929)
-- Name: route_statique_idroute_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.route_statique_idroute_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.route_statique_idroute_seq OWNER TO postgres;

--
-- TOC entry 4996 (class 0 OID 0)
-- Dependencies: 227
-- Name: route_statique_idroute_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.route_statique_idroute_seq OWNED BY public.route_statique.idroute;


--
-- TOC entry 220 (class 1259 OID 25864)
-- Name: utilisateur; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.utilisateur (
    idutilisateur integer NOT NULL,
    pseudo character varying(50) NOT NULL,
    motdepasse character varying(255) NOT NULL
);


ALTER TABLE public.utilisateur OWNER TO postgres;

--
-- TOC entry 219 (class 1259 OID 25863)
-- Name: utilisateur_idutilisateur_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.utilisateur_idutilisateur_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.utilisateur_idutilisateur_seq OWNER TO postgres;

--
-- TOC entry 4997 (class 0 OID 0)
-- Dependencies: 219
-- Name: utilisateur_idutilisateur_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.utilisateur_idutilisateur_seq OWNED BY public.utilisateur.idutilisateur;


--
-- TOC entry 4791 (class 2604 OID 25895)
-- Name: equipement idequipement; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipement ALTER COLUMN idequipement SET DEFAULT nextval('public.equipement_idequipement_seq'::regclass);


--
-- TOC entry 4792 (class 2604 OID 25911)
-- Name: interface idinterface; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.interface ALTER COLUMN idinterface SET DEFAULT nextval('public.interface_idinterface_seq'::regclass);


--
-- TOC entry 4794 (class 2604 OID 25949)
-- Name: interface_reseau idinterface; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.interface_reseau ALTER COLUMN idinterface SET DEFAULT nextval('public.interface_reseau_idinterface_seq'::regclass);


--
-- TOC entry 4795 (class 2604 OID 25964)
-- Name: lien_reseau idlien; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lien_reseau ALTER COLUMN idlien SET DEFAULT nextval('public.lien_reseau_idlien_seq'::regclass);


--
-- TOC entry 4790 (class 2604 OID 25879)
-- Name: reseau idreseau; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reseau ALTER COLUMN idreseau SET DEFAULT nextval('public.reseau_idreseau_seq'::regclass);


--
-- TOC entry 4793 (class 2604 OID 25933)
-- Name: route_statique idroute; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.route_statique ALTER COLUMN idroute SET DEFAULT nextval('public.route_statique_idroute_seq'::regclass);


--
-- TOC entry 4789 (class 2604 OID 25867)
-- Name: utilisateur idutilisateur; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.utilisateur ALTER COLUMN idutilisateur SET DEFAULT nextval('public.utilisateur_idutilisateur_seq'::regclass);


--
-- TOC entry 4976 (class 0 OID 25892)
-- Dependencies: 224
-- Data for Name: equipement; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.equipement (idequipement, nomequipement, typeequipement, idutilisateur, x, y) FROM stdin;
11	PC Meud	Hote	1	380	207
5	Routeur 1	Routeur	1	-624	21
10	Routeur 2	Routeur	1	-325	-231
7	PC Ayyub	Hote	1	-873	-273
9	PC Robin	Hote	1	10	-438
13	Routeur 3	Routeur	1	59	31
\.


--
-- TOC entry 4978 (class 0 OID 25908)
-- Dependencies: 226
-- Data for Name: interface; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.interface (idinterface, adresseip, adressemac, idequipement, idreseau) FROM stdin;
7	192.168.1.1	00:30:84:9d:e4:c5	7	2
8	192.168.1.254	00:00:0c:06:13:4a	5	2
9	192.168.2.254	00:50:ba:c5:6a:2a	5	5
11	192.168.2.253	00:00:0c:06:13:8a	10	5
12	192.168.3.254	00:00:0c:06:13:3a	10	6
13	192.168.3.1	00:00:0c:06:18:3a	9	6
14	192.168.5.1	02:60:9c:2e:b5:6b	11	8
16	192.168.5.254	00:00:0c:06:20:8a	13	8
17	192.168.2.252	02:64:8c:2e:b5:8b	13	5
\.


--
-- TOC entry 4982 (class 0 OID 25946)
-- Dependencies: 230
-- Data for Name: interface_reseau; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.interface_reseau (idinterface, idequipement, nominterface, adresseip, adressemac, idsousreseau, masque, passerelle) FROM stdin;
\.


--
-- TOC entry 4984 (class 0 OID 25961)
-- Dependencies: 232
-- Data for Name: lien_reseau; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.lien_reseau (idlien, idequipementsource, idequipementdestination, interfacesource, interfacedestination, typelien) FROM stdin;
\.


--
-- TOC entry 4985 (class 0 OID 25984)
-- Dependencies: 233
-- Data for Name: position_equipement; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.position_equipement (idequipement, posx, posy) FROM stdin;
\.


--
-- TOC entry 4974 (class 0 OID 25876)
-- Dependencies: 222
-- Data for Name: reseau; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.reseau (idreseau, adressereseau, masquecidr, idutilisateur, x, y) FROM stdin;
8	192.168.5.0	24	1	379	8
5	192.168.2.0	24	1	-333	29
2	192.168.1.0	24	1	-618	-255
6	192.168.3.0	24	1	11	-220
\.


--
-- TOC entry 4980 (class 0 OID 25930)
-- Dependencies: 228
-- Data for Name: route_statique; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.route_statique (idroute, reseaudestination, prochainsaut, idequipement) FROM stdin;
7	0.0.0.0	192.168.1.254	7
9	0.0.0.0	192.168.3.254	9
10	192.168.3.0	192.168.2.253	5
11	192.168.1.0	192.168.2.254	10
12	192.168.5.0	192.168.2.252	10
13	192.168.3.0	192.168.2.253	13
14	0.0.0.0	192.168.5.254	11
16	0.0.0.0	192.168.2.252	5
17	0.0.0.0	192.168.2.254	13
\.


--
-- TOC entry 4972 (class 0 OID 25864)
-- Dependencies: 220
-- Data for Name: utilisateur; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.utilisateur (idutilisateur, pseudo, motdepasse) FROM stdin;
1	admin	admin123
\.


--
-- TOC entry 4998 (class 0 OID 0)
-- Dependencies: 223
-- Name: equipement_idequipement_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.equipement_idequipement_seq', 16, true);


--
-- TOC entry 4999 (class 0 OID 0)
-- Dependencies: 225
-- Name: interface_idinterface_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.interface_idinterface_seq', 23, true);


--
-- TOC entry 5000 (class 0 OID 0)
-- Dependencies: 229
-- Name: interface_reseau_idinterface_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.interface_reseau_idinterface_seq', 1, false);


--
-- TOC entry 5001 (class 0 OID 0)
-- Dependencies: 231
-- Name: lien_reseau_idlien_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.lien_reseau_idlien_seq', 1, false);


--
-- TOC entry 5002 (class 0 OID 0)
-- Dependencies: 221
-- Name: reseau_idreseau_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.reseau_idreseau_seq', 10, true);


--
-- TOC entry 5003 (class 0 OID 0)
-- Dependencies: 227
-- Name: route_statique_idroute_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.route_statique_idroute_seq', 17, true);


--
-- TOC entry 5004 (class 0 OID 0)
-- Dependencies: 219
-- Name: utilisateur_idutilisateur_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.utilisateur_idutilisateur_seq', 1, true);


--
-- TOC entry 4804 (class 2606 OID 25901)
-- Name: equipement equipement_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipement
    ADD CONSTRAINT equipement_pkey PRIMARY KEY (idequipement);


--
-- TOC entry 4806 (class 2606 OID 25918)
-- Name: interface interface_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.interface
    ADD CONSTRAINT interface_pkey PRIMARY KEY (idinterface);


--
-- TOC entry 4810 (class 2606 OID 25954)
-- Name: interface_reseau interface_reseau_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.interface_reseau
    ADD CONSTRAINT interface_reseau_pkey PRIMARY KEY (idinterface);


--
-- TOC entry 4812 (class 2606 OID 25973)
-- Name: lien_reseau lien_reseau_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lien_reseau
    ADD CONSTRAINT lien_reseau_pkey PRIMARY KEY (idlien);


--
-- TOC entry 4814 (class 2606 OID 25991)
-- Name: position_equipement position_equipement_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.position_equipement
    ADD CONSTRAINT position_equipement_pkey PRIMARY KEY (idequipement);


--
-- TOC entry 4802 (class 2606 OID 25885)
-- Name: reseau reseau_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reseau
    ADD CONSTRAINT reseau_pkey PRIMARY KEY (idreseau);


--
-- TOC entry 4808 (class 2606 OID 25939)
-- Name: route_statique route_statique_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.route_statique
    ADD CONSTRAINT route_statique_pkey PRIMARY KEY (idroute);


--
-- TOC entry 4798 (class 2606 OID 25872)
-- Name: utilisateur utilisateur_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.utilisateur
    ADD CONSTRAINT utilisateur_pkey PRIMARY KEY (idutilisateur);


--
-- TOC entry 4800 (class 2606 OID 25874)
-- Name: utilisateur utilisateur_pseudo_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.utilisateur
    ADD CONSTRAINT utilisateur_pseudo_key UNIQUE (pseudo);


--
-- TOC entry 4816 (class 2606 OID 25902)
-- Name: equipement fk_equipement_utilisateur; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipement
    ADD CONSTRAINT fk_equipement_utilisateur FOREIGN KEY (idutilisateur) REFERENCES public.utilisateur(idutilisateur);


--
-- TOC entry 4817 (class 2606 OID 25919)
-- Name: interface fk_interface_equipement; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.interface
    ADD CONSTRAINT fk_interface_equipement FOREIGN KEY (idequipement) REFERENCES public.equipement(idequipement);


--
-- TOC entry 4818 (class 2606 OID 25924)
-- Name: interface fk_interface_reseau; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.interface
    ADD CONSTRAINT fk_interface_reseau FOREIGN KEY (idreseau) REFERENCES public.reseau(idreseau);


--
-- TOC entry 4815 (class 2606 OID 25886)
-- Name: reseau fk_reseau_utilisateur; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reseau
    ADD CONSTRAINT fk_reseau_utilisateur FOREIGN KEY (idutilisateur) REFERENCES public.utilisateur(idutilisateur);


--
-- TOC entry 4819 (class 2606 OID 25940)
-- Name: route_statique fk_route_equipement; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.route_statique
    ADD CONSTRAINT fk_route_equipement FOREIGN KEY (idequipement) REFERENCES public.equipement(idequipement);


--
-- TOC entry 4820 (class 2606 OID 25955)
-- Name: interface_reseau interface_reseau_idequipement_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.interface_reseau
    ADD CONSTRAINT interface_reseau_idequipement_fkey FOREIGN KEY (idequipement) REFERENCES public.equipement(idequipement) ON DELETE CASCADE;


--
-- TOC entry 4821 (class 2606 OID 25979)
-- Name: lien_reseau lien_reseau_idequipementdestination_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lien_reseau
    ADD CONSTRAINT lien_reseau_idequipementdestination_fkey FOREIGN KEY (idequipementdestination) REFERENCES public.equipement(idequipement) ON DELETE CASCADE;


--
-- TOC entry 4822 (class 2606 OID 25974)
-- Name: lien_reseau lien_reseau_idequipementsource_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lien_reseau
    ADD CONSTRAINT lien_reseau_idequipementsource_fkey FOREIGN KEY (idequipementsource) REFERENCES public.equipement(idequipement) ON DELETE CASCADE;


--
-- TOC entry 4823 (class 2606 OID 25992)
-- Name: position_equipement position_equipement_idequipement_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.position_equipement
    ADD CONSTRAINT position_equipement_idequipement_fkey FOREIGN KEY (idequipement) REFERENCES public.equipement(idequipement) ON DELETE CASCADE;


