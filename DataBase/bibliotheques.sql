DROP DATABASE IF EXISTS bibliotheque;
CREATE DATABASE bibliotheque CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE bibliotheque;


CREATE TABLE utilisateur (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    role ENUM('bibliothecaire', 'adherent') NOT NULL DEFAULT 'adherent'
) ENGINE=InnoDB;


CREATE TABLE adherent (
    id_adherent INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL UNIQUE,
    numero_carte VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    telephone VARCHAR(20),
    statut ENUM('actif', 'suspendu') NOT NULL DEFAULT 'actif',
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE livre (
    id_livre INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20) NOT NULL UNIQUE,
    titre VARCHAR(150) NOT NULL,
    auteur VARCHAR(100) NOT NULL,
    categorie VARCHAR(50),
    couverture VARCHAR(255)
) ENGINE=InnoDB;


CREATE TABLE exemplaire (
    id_exemplaire INT AUTO_INCREMENT PRIMARY KEY,
    id_livre INT NOT NULL,
    code_barre VARCHAR(30) NOT NULL UNIQUE,
    etat ENUM('neuf', 'bon', 'use', 'abime') NOT NULL DEFAULT 'bon',
    disponible TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (id_livre) REFERENCES livre(id_livre) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE emprunt (
    id_emprunt INT AUTO_INCREMENT PRIMARY KEY,
    id_adherent INT NOT NULL,
    id_exemplaire INT NOT NULL,
    date_emprunt DATE NOT NULL,
    date_limite DATE NOT NULL,
    date_retour DATE DEFAULT NULL,
    penalite DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_adherent) REFERENCES adherent(id_adherent),
    FOREIGN KEY (id_exemplaire) REFERENCES exemplaire(id_exemplaire)
) ENGINE=InnoDB;


CREATE TABLE reservation (
    id_reservation INT AUTO_INCREMENT PRIMARY KEY,
    id_adherent INT NOT NULL,
    id_livre INT NOT NULL,
    date_reservation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rang INT NOT NULL DEFAULT 1,
    statut ENUM('en_attente', 'disponible', 'annulee', 'terminee') NOT NULL DEFAULT 'en_attente',
    FOREIGN KEY (id_adherent) REFERENCES adherent(id_adherent) ON DELETE CASCADE,
    FOREIGN KEY (id_livre) REFERENCES livre(id_livre) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE parametre (
    cle VARCHAR(50) PRIMARY KEY,
    valeur VARCHAR(100) NOT NULL
) ENGINE=InnoDB;


CREATE TABLE notification (
    id_notification INT AUTO_INCREMENT PRIMARY KEY,
    id_adherent INT NOT NULL,
    id_emprunt INT DEFAULT NULL,
    id_reservation INT DEFAULT NULL,
    type_notification ENUM('rappel_echeance', 'retard', 'reservation_disponible') NOT NULL,
    date_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    envoye TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_adherent) REFERENCES adherent(id_adherent) ON DELETE CASCADE,
    FOREIGN KEY (id_emprunt) REFERENCES emprunt(id_emprunt) ON DELETE CASCADE,
    FOREIGN KEY (id_reservation) REFERENCES reservation(id_reservation) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE INDEX idx_livre_titre ON livre(titre);
CREATE INDEX idx_livre_auteur ON livre(auteur);
CREATE INDEX idx_livre_categorie ON livre(categorie);
CREATE INDEX idx_adherent_nom ON adherent(nom, prenom);
CREATE INDEX idx_emprunt_limite ON emprunt(date_limite, date_retour);


INSERT INTO parametre (cle, valeur) VALUES
('duree_pret_jours', '14'),
('max_emprunts', '3'),
('penalite_par_jour', '100');

-- mot de passe des deux comptes de test : admin123
INSERT INTO utilisateur (login, email, mot_de_passe_hash, role) VALUES
('admin', 'admin@biblio.com', '$2y$10$2OXLI5zzpNVTbbsl7iEJcu4cfD8gZOMFnnyvEMXTtbg8exDds1kvC', 'bibliothecaire'),
('adherent1', 'adherent1@biblio.com', '$2y$10$2OXLI5zzpNVTbbsl7iEJcu4cfD8gZOMFnnyvEMXTtbg8exDds1kvC', 'adherent');

INSERT INTO adherent (id_utilisateur, numero_carte, nom, prenom, telephone) VALUES
(2, 'AD-0001', 'hope', 'Mickaelson', '695254346');

INSERT INTO livre (isbn, titre, auteur, categorie) VALUES
('9782070368228', 'Le Petit Prince', 'Antoine de Saint-Exupery', 'Roman'),
('9782253006329', 'Les Miserables', 'Victor Hugo', 'Roman'),
('9782100545261', 'Algorithmique', 'Thomas Cormen', 'Informatique');

INSERT INTO exemplaire (id_livre, code_barre, etat) VALUES
(1, 'EX-0001', 'bon'),
(1, 'EX-0002', 'neuf'),
(2, 'EX-0003', 'bon'),
(3, 'EX-0004', 'neuf');