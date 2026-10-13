USE bibliotheque;

ALTER TABLE utilisateur
    ADD COLUMN email_verifie TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE code_verification_email (
    id_utilisateur INT NOT NULL PRIMARY KEY,
    code_hash VARCHAR(255) NOT NULL,
    expire_le DATETIME NOT NULL,
    tentatives TINYINT UNSIGNED NOT NULL DEFAULT 0,
    envoye_le DATETIME NOT NULL,
    CONSTRAINT fk_code_verification_utilisateur
        FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur(id_utilisateur)
        ON DELETE CASCADE
) ENGINE=InnoDB;
