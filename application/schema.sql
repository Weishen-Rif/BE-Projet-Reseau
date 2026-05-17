CREATE TABLE IF NOT EXISTS utilisateur (
    idutilisateur SERIAL PRIMARY KEY,
    pseudo VARCHAR(50) NOT NULL UNIQUE,
    motdepasse VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS reseau (
    idreseau SERIAL PRIMARY KEY,
    adressereseau VARCHAR(18) NOT NULL,
    masquecidr SMALLINT NOT NULL CHECK (masquecidr BETWEEN 1 AND 32),
    idutilisateur INTEGER NOT NULL REFERENCES utilisateur(idutilisateur) ON DELETE CASCADE,
    x FLOAT,
    y FLOAT
);

CREATE TABLE IF NOT EXISTS equipement (
    idequipement SERIAL PRIMARY KEY,
    nomequipement VARCHAR(100) NOT NULL,
    typeequipement VARCHAR(20) NOT NULL CHECK (typeequipement IN ('Routeur', 'Hote')),
    idutilisateur INTEGER NOT NULL REFERENCES utilisateur(idutilisateur) ON DELETE CASCADE,
    x FLOAT,
    y FLOAT
);

CREATE TABLE IF NOT EXISTS interface (
    idinterface SERIAL PRIMARY KEY,
    nominterface VARCHAR(50) NOT NULL,
    adresseip VARCHAR(20),
    adressemac VARCHAR(17),
    idequipement INTEGER NOT NULL REFERENCES equipement(idequipement) ON DELETE CASCADE,
    idreseau INTEGER NOT NULL REFERENCES reseau(idreseau) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS route_statique (
    idroute SERIAL PRIMARY KEY,
    reseaudestination VARCHAR(18) NOT NULL,
    prochainsaut VARCHAR(20) NOT NULL,
    idequipement INTEGER NOT NULL REFERENCES equipement(idequipement) ON DELETE CASCADE
);
