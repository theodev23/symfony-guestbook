# Conference Guestbook — Symfony 8.1

Application web développée avec Symfony 8.1 autour d'un système de conférences et de commentaires.

Ce projet m'a permis d'approfondir l'écosystème Symfony à travers un cas concret : soumission et modération de commentaires, traitements asynchrones, notifications, API REST, cache HTTP, internationalisation et déploiement en production.

Le projet suit le fil conducteur du **Symfony Fast Track**. Mon objectif n'était pas simplement de reproduire le tutoriel, mais de comprendre, configurer, déboguer et faire fonctionner ensemble les différentes briques du framework, en local puis dans un environnement de production.

**Démo en ligne :**  
https://main-bvxea6i-n4byujhwlf4fe.fr-4.platformsh.site/

## Aperçu de l'application

### Accueil

La page d'accueil présente les différentes conférences disponibles.

![Page d'accueil de Conference Guestbook](docs/screenshots/homepage.png)

### Consultation d'une conférence

Chaque conférence dispose de sa propre page permettant de consulter les commentaires publiés et d'en soumettre de nouveaux.

![Page d'une conférence et formulaire de commentaire](docs/screenshots/conference.png)

### API REST

Les conférences et commentaires sont également exposés via une API REST construite avec API Platform.

![Documentation de l'API avec API Platform](docs/screenshots/api.png)

## Fonctionnalités principales

- Gestion de conférences et de commentaires avec Doctrine ORM
- Back-office d'administration avec EasyAdmin
- Authentification et contrôle d'accès
- Workflow de modération des commentaires
- Détection de spam avec Symfony AI et OpenAI
- Traitements asynchrones avec Symfony Messenger et RabbitMQ
- Notifications de modération par email et intégration Slack avec Symfony Notifier
- Actions d'acceptation et de rejet des commentaires
- Redimensionnement des images avant publication
- Gestion des sessions avec Redis
- Cache HTTP avec Varnish
- API REST avec API Platform
- Interface dynamique avec Turbo et Stimulus
- Internationalisation français / anglais
- Tests avec PHPUnit, Foundry et Panther
- Environnement local avec Docker
- Déploiement sur Upsun

## Architecture générale

Le traitement d'un commentaire est découplé afin de ne pas faire dépendre la réponse HTTP de traitements potentiellement longs.

```text
Utilisateur
    │
    ▼
Soumission du commentaire
    │
    ├── Validation du formulaire
    └── Enregistrement en base
    │
    ▼
Symfony Messenger
    │
    ▼
RabbitMQ
    │
    ▼
Worker Messenger
    │
    ▼
Analyse anti-spam
    │
    ▼
Symfony Workflow
    │
    ├── spam ───────────────────────→ fin
    │
    └── ham / potential_spam
                │
                ▼
        Notification de revue
          │             │
        Email         Slack
                │
                ▼
        Décision administrateur
           │             │
        Rejet          Acceptation
           │             │
           ▼             ▼
        rejected      Messenger
                         │
                         ▼
                 Optimisation image
                         │
                         ▼
                     published
```

Cette architecture permet notamment d'éviter qu'un appel à une API externe, le traitement d'une image ou l'envoi d'une notification ne bloque la requête HTTP de l'utilisateur.

## Stack technique

**Backend :** PHP 8.5, Symfony 8.1, Doctrine ORM, PostgreSQL

**Asynchrone :** Symfony Messenger, RabbitMQ

**Sessions :** Redis

**Cache HTTP :** Symfony HTTP Cache, ESI, Varnish

**Frontend :** Twig, Bootstrap, AssetMapper, Turbo, Stimulus

**API :** API Platform

**IA et notifications :** Symfony AI, OpenAI, Symfony Mailer, Symfony Notifier, Slack

**Tests :** PHPUnit, Zenstruck Foundry, Panther

**Infrastructure :** Docker, Upsun

## Points techniques approfondis

### Symfony Messenger et RabbitMQ

Lorsqu'un commentaire est soumis, il est d'abord enregistré en base de données. Un message est ensuite envoyé via Symfony Messenger.

RabbitMQ joue le rôle de **message broker** : il conserve les messages dans une file d'attente jusqu'à ce qu'un worker Messenger les consomme.

```text
Requête HTTP
    ↓
Commentaire enregistré
    ↓
Message envoyé à RabbitMQ
    ↓
Réponse HTTP rendue à l'utilisateur

En parallèle :

RabbitMQ
    ↓
Worker Messenger
    ↓
Traitement du commentaire
```

Cette architecture permet de sortir les traitements longs du cycle de la requête HTTP.

Le worker est également utilisé pour les étapes suivantes du workflow, notamment les notifications et le traitement final avant publication.

### Workflow de modération

Le cycle de vie d'un commentaire est modélisé avec Symfony Workflow.

```text
submitted
│
├── accept ──────────────→ ham
│                         ├── publish_ham → ready → optimize → published
│                         └── reject_ham ────────────────→ rejected
│
├── might_be_spam ───────→ potential_spam
│                         ├── publish → ready → optimize → published
│                         └── reject ────────────────────→ rejected
│
└── reject_spam ─────────→ spam
```

Les changements d'état passent par des **transitions explicites**, ce qui permet de centraliser les règles métier au lieu de modifier directement la propriété `state` à différents endroits du code.

### Détection de spam

Les nouveaux commentaires sont analysés avec **Symfony AI** et l'API OpenAI.

Le résultat de cette analyse détermine la première transition du workflow :

- commentaire légitime ;
- commentaire potentiellement indésirable ;
- spam manifeste.

L'appel à l'API étant réalisé par un worker Messenger, l'utilisateur n'a pas besoin d'attendre la réponse du modèle pendant la soumission du formulaire.

### Modération et notifications

Lorsqu'une validation humaine est nécessaire, Symfony Notifier prévient l'administrateur.

La modération fonctionne notamment par **email** : la notification contient les informations du commentaire ainsi que des actions permettant de l'accepter ou de le rejeter.

Le projet intègre également **Slack** comme canal complémentaire. Les notifications Slack peuvent elles aussi contenir des boutons `Accept` et `Reject`.

Les notifications sont envoyées de manière asynchrone grâce à Messenger.

Cette partie m'a permis de travailler avec :

- Symfony Notifier ;
- Symfony Mailer ;
- Slack API ;
- Messenger ;
- les variables d'environnement et secrets de production.

### Traitement des images

Les utilisateurs peuvent joindre une photo à leur commentaire.

Avant publication, le fichier peut être redimensionné afin d'éviter de conserver et servir des images inutilement volumineuses.

Le traitement est réalisé par le worker Messenger avant le passage du commentaire à l'état `published`.

### Redis

Redis est utilisé comme backend de stockage des sessions de l'application.

Cette partie du projet m'a permis de mieux comprendre la différence entre :

- stockage persistant en base de données ;
- stockage en mémoire ;
- session utilisateur ;
- services d'infrastructure accessibles depuis l'application.

### Cache HTTP et Varnish

Le projet utilise les mécanismes de cache HTTP de Symfony ainsi que Varnish en production.

Le principe est de servir directement une réponse mise en cache lorsqu'elle est encore valide, sans exécuter systématiquement toute l'application Symfony.

```text
Client
   ↓
Varnish
   ↓
Réponse en cache ?
   ├── Oui → réponse immédiate
   │
   └── Non → Symfony → réponse → mise en cache
```

Les ESI permettent également de mettre en cache différents fragments d'une même page indépendamment.

### API Platform

API Platform expose une API REST permettant notamment d'accéder aux conférences et aux commentaires.

La documentation interactive est accessible via :

```text
/api
```

Des groupes de sérialisation permettent de contrôler les propriétés exposées.

Une extension Doctrine garantit également que seuls les commentaires ayant atteint l'état `published` sont accessibles publiquement via l'API.

### Symfony UX

Le projet utilise **Turbo** et **Stimulus**.

Turbo améliore la navigation en évitant certains rechargements complets de page.

Stimulus permet d'ajouter des comportements JavaScript ciblés sans construire une application frontend séparée.

Il est notamment utilisé pour afficher un aperçu d'une image sélectionnée avant l'envoi du formulaire.

### Internationalisation

L'interface est disponible en anglais et en français.

Symfony Translation est utilisé pour :

- traduire les textes de l'interface ;
- gérer les pluriels ;
- adapter les dates et nombres à la locale ;
- intégrer la langue directement dans les URLs.

Exemples :

```text
/en/
/fr/
```

## Lancer le projet en local

### Prérequis

- PHP 8.5
- Composer
- Symfony CLI
- Docker

### Installation

Cloner le dépôt :

```bash
git clone https://github.com/theodev23/symfony-guestbook.git
cd symfony-guestbook
```

Installer les dépendances PHP :

```bash
composer install
```

Démarrer les services Docker :

```bash
docker compose up -d
```

Créer la base de données si nécessaire :

```bash
symfony console doctrine:database:create
```

Appliquer les migrations :

```bash
symfony console doctrine:migrations:migrate
```

Démarrer le serveur Symfony :

```bash
symfony server:start -d
```

L'application est alors accessible via l'URL indiquée par Symfony CLI.

### Lancer le worker Messenger

Les traitements asynchrones nécessitent un worker :

```bash
symfony console messenger:consume async -vv
```

Le worker doit rester actif pendant les tests des fonctionnalités asynchrones.

## Tests

La suite de tests peut être lancée avec :

```bash
make tests
```

Le projet utilise notamment :

- PHPUnit ;
- Zenstruck Foundry ;
- Panther.

Les tests couvrent différentes couches de l'application, des services aux scénarios fonctionnels.

## Configuration et secrets

Les clés d'API et identifiants sensibles ne sont pas versionnés dans le dépôt.

Pour utiliser la détection de spam par IA, une clé OpenAI doit être configurée :

```text
OPENAI_API_KEY
```

L'intégration Slack nécessite également :

```text
SLACK_DSN
```

L'adresse utilisée pour les notifications de modération peut être personnalisée avec :

```text
ADMIN_EMAIL
```

La configuration de l'envoi d'emails repose sur :

```text
MAILER_DSN
```

Symfony Secrets et les variables d'environnement permettent de séparer la configuration sensible du code source.

Les clés privées de déchiffrement et tokens d'accès ne doivent jamais être versionnés.

## Déploiement

L'application est déployée sur **Upsun**.

**Production :**  
https://main-bvxea6i-n4byujhwlf4fe.fr-4.platformsh.site/

L'environnement comprend notamment :

```text
Application Symfony
├── PostgreSQL
├── Redis
├── RabbitMQ
├── Worker Messenger
├── Varnish
└── Stockage partagé
```

Le worker Messenger est exécuté comme un processus dédié en production afin de consommer en continu les messages RabbitMQ.

Cette partie du projet m'a permis de travailler sur la différence entre :

- environnement local ;
- environnement de test ;
- environnement de production ;
- configuration applicative ;
- configuration d'infrastructure ;
- variables d'environnement et secrets ;
- workers longue durée.

## Difficultés rencontrées et apprentissages

Une partie importante du projet a consisté à résoudre des problèmes qui n'étaient pas directement liés à l'écriture du code métier.

J'ai notamment travaillé sur :

- la configuration de services Docker ;
- les différences entre les versions des outils et la documentation ;
- le fonctionnement des workers longue durée ;
- la configuration et le diagnostic de RabbitMQ ;
- le traitement des messages Messenger en échec ;
- la configuration des notifications email et Slack ;
- les extensions PHP nécessaires à certains services ;
- le cache HTTP et Varnish ;
- les variables d'environnement et les secrets Symfony ;
- les tunnels SSH vers les services Upsun ;
- le déploiement et le diagnostic d'erreurs en production.

Ces difficultés ont constitué une partie importante de l'intérêt du projet, car elles m'ont permis d'aller au-delà de l'écriture de contrôleurs et de templates pour mieux comprendre l'environnement dans lequel fonctionne une application Symfony.

## Contexte du projet

Ce projet s'appuie sur le **Symfony Fast Track**, utilisé comme fil conducteur pour explorer progressivement l'écosystème Symfony dans une application complète.

Le travail réalisé a principalement porté sur la compréhension, la configuration et l'intégration des différentes briques : Doctrine, Security, Forms, Messenger, Workflow, Notifier, API Platform, Symfony UX, Redis, RabbitMQ, cache HTTP et déploiement sur Upsun.

## Auteur

**Théo Devarenne**

Étudiant en Master Informatique à l'Université de Montpellier

GitHub : [@theodev23](https://github.com/theodev23)