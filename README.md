# Application de bibliothèque

## Prérequis

- PHP 8.1 ou plus récent avec `pdo_mysql` et `mbstring`
- MySQL ou MariaDB (par exemple via XAMPP)
- Composer
- Un compte SMTP pour l’envoi des codes de vérification

## Installation locale

1. Importez `DataBase/bibliotheques.sql` dans phpMyAdmin pour créer la base `bibliotheque` et ses tables.
2. Exécutez `DataBase/authentification.sql` une seule fois pour ajouter la vérification des adresses e-mail.
3. Copiez `.env.example` vers `.env`, puis renseignez les paramètres MySQL et SMTP.
4. Installez les dépendances avec `composer install`.
5. Ouvrez le projet via Apache/PHP, par exemple `http://localhost/biblio/`.

À l’inscription, le compte adhérent est créé en attente de confirmation. Un code à 8 chiffres, valable 10 minutes, est envoyé à l’adresse saisie. Après confirmation, la connexion se fait avec l’adresse e-mail et le mot de passe choisis. Les mots de passe et codes temporaires sont stockés sous forme de hachages.

Le fichier `.env` contient des secrets et ne doit jamais être ajouté à Git.
