# Sylius E-Invoice Pennylane Plugin

> English: [README.md](README.md) <!-- vitrine:lang -->

[![Build](https://github.com/thibmonier/sylius-einvoice-pennylane-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-einvoice-pennylane-plugin/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Envoyez les factures Factur-X de votre boutique Sylius à Pennylane : le client est retrouvé ou créé, puis la facture est importée par l'API Company v2 de Pennylane.**

Premier adaptateur réel de [`tmonier/sylius-einvoice-transmission-plugin`](https://github.com/thibmonier/sylius-einvoice-transmission-plugin) : ce plugin fournit le port, le journal des envois et l'écouteur des factures générées par [`tmonier/sylius-facturx-plugin`](https://github.com/thibmonier/sylius-facturx-plugin) ; celui-ci implémente `InvoiceTransmitterInterface` pour Pennylane.

> **Aucune promesse réglementaire.** Ce plugin est une brique technique qui appelle une API. Il ne rend aucune facture conforme et n'affirme pas que Pennylane est une plateforme agréée de facturation électronique. Le statut de Pennylane est à vérifier sur la [liste officielle de la DGFiP](https://www.impots.gouv.fr/je-consulte-la-liste-des-plateformes-agreees). Ce n'est pas un conseil juridique ou fiscal. Les appels ont été écrits d'après la référence de l'API Pennylane et sont testés contre des réponses simulées, jamais contre le vrai service : voir [Ce qui n'est pas encore confirmé](#ce-qui-nest-pas-encore-confirmé).

## Fonctionnalités

- **Adaptateur** `Tmonier\SyliusEinvoicePennylanePlugin\Transmission\PennylaneInvoiceTransmitter`, à déclarer comme `transmitter` du plugin de transmission. Le premier envoi d'une facture passe par l'écouteur idempotent de ce plugin ; un envoi en erreur peut être relancé avec son `RelaunchTransmissionHandler`.
- **Client retrouvé ou créé.** L'acheteur est lu dans le XML Factur-X (`BuyerTradeParty` : nom, adresse, SIREN, numéro de TVA, e-mail). Le client est recherché dans Pennylane par `external_reference` ; il est créé s'il n'existe pas, et jamais créé deux fois pour la même référence. Un client existant n'est pas mis à jour.
- **Facture importée.** Le PDF Factur-X est envoyé à Pennylane pour ce client. En cas de succès, la ligne du journal reçoit le statut `deposited` et le message `Imported in Pennylane: invoice id <id> (customer id <id>).` (message en anglais, tel qu'écrit par le code).
- **Erreurs écrites pour le journal.** Chaque échec devient un statut et un message du journal (tableau ci-dessous). Les messages ne portent que le statut HTTP : jamais le jeton, jamais le corps de la requête ni de la réponse.
- **Aucune reprise automatique.** Un appel en échec est signalé une fois ; l'envoi est relancé explicitement.
- **Jeton dans l'environnement.** `PENNYLANE_API_TOKEN`, jamais dans le code. Sans jeton, aucune requête n'est envoyée et l'envoi se termine en `error`.

### Points d'API Pennylane utilisés

Tous sur l'hôte de `base_url` (par défaut `https://app.pennylane.com`), avec le jeton en `Bearer` :

| Appel | Point d'accès | Rôle |
|-------|---------------|------|
| `findCustomerId()` | `GET /api/external/v2/customers`, `filter=[{"field":"external_reference","operator":"eq","value":"<référence>"}]`, `limit=1` | Retrouver le client |
| `createCustomer()` | `POST /api/external/v2/customers` (JSON) | Le créer s'il est introuvable : `external_reference`, `billing_address`, `emails`, et pour une entreprise `customer_type`, `name`, `reg_no`, `vat_number` ; pour un particulier `customer_type`, `first_name`, `last_name` |
| `importInvoice()` | `POST /api/external/v2/customer_invoices/e_invoices/imports` (multipart) | Importer la facture : `file` (le PDF, `application/pdf`) et `invoice_options` (`{"customer_id": <id>}`) |

Référence de l'API : [clients](https://pennylane.readme.io/reference/getcustomers), [création d'un client](https://pennylane.readme.io/reference/postcustomer), [import de facture électronique](https://pennylane.readme.io/reference/createcustomerinvoiceeinvoiceimport), [changelog du point d'import](https://pennylane.readme.io/changelog/customer-supplier-invoices-dedicated-e-invoice-import-endpoints).

Le jeton doit avoir la portée **`customer_invoices:all`** (import). Les messages du plugin et la configuration citent aussi **`customers:all`** pour la recherche et la création du client : accordez les deux.

### L'`external_reference` du client

Le document Factur-X ne porte pas le code client de Sylius : la référence est donc construite à partir du document :

- acheteur avec un SIREN : `siren-<SIREN>` ;
- tout autre acheteur (un particulier, ou une entreprise qui n'a qu'un numéro de TVA dans le document) : `buyer-` suivi des 32 premiers caractères d'un SHA-256 du nom en minuscules, du code postal en minuscules et du code pays. Un acheteur avec un numéro de TVA est tout de même créé comme entreprise dans Pennylane.

**Limite :** un particulier qui change de nom ou de code postal est vu comme un nouveau client dans Pennylane. Deux particuliers différents de même nom et de même code postal sont le même client. Les entreprises sont identifiées par leur SIREN, qui ne change pas.

### Statuts du journal

| Situation | Statut | Message |
|-----------|--------|---------|
| Facture importée | `deposited` | `Imported in Pennylane: invoice id … (customer id …).` |
| Aucun jeton configuré | `error` | jeton non configuré |
| HTTP 401 ou 403 | `error` | jeton ou portée refusés (code HTTP) |
| HTTP 429 | `error` | limite de débit atteinte, avec `Retry-After` en secondes s'il est fourni |
| HTTP 5xx, délai dépassé, échec de connexion | `error` | Pennylane indisponible |
| Réponse sans `id` entier, ou liste non comprise | `error` | réponse non comprise |
| XML Factur-X invalide ou sans `BuyerTradeParty` | `error` | rien n'est envoyé |
| Tout autre statut HTTP à partir de 300 (400, 404, 422…) | `rejected` | Pennylane a refusé la requête (code HTTP) |

Les statuts sont ceux du plugin de transmission (`TransmissionStatus`). Un envoi en `error` peut être relancé ; un envoi `rejected` est la réponse de Pennylane sur le contenu et n'est pas fait pour être renvoyé tel quel.

## Ce qui n'est pas encore confirmé

À vérifier contre le **bac à sable** de Pennylane avant de vous appuyer sur le plugin ; les tests unitaires et d'intégration utilisent un client HTTP scripté et ne peuvent donc pas le dire :

- le champ `invoice_options` est envoyé comme un **champ de formulaire encodé en JSON** de la requête multipart, à côté de `file` ; la forme attendue par l'API n'est pas confirmée ;
- tout HTTP **400, 404 et 422** est converti en `rejected` ; savoir si certains devraient être relancés (`error`) n'est pas confirmé ;
- les champs exacts acceptés à la création d'un client et les portées requises suivent la référence au moment de l'écriture et peuvent changer ;
- le **statut de Pennylane** comme plateforme agréée (voir l'avertissement ci-dessus).

## Captures d'écran

Ce plugin n'a pas d'interface (le plugin de transmission non plus) : il agit à la génération de la facture Factur-X et alimente la table du journal `tmonier_einvoice_transmission`. Exemple de ligne après un envoi réussi (valeurs fictives) :

```text
invoice_id     : 0b6f1c2e-5d3a-4c1b-9a77-2f4e8d1c6a10
invoice_number : 2026/00042
status         : deposited
message        : Imported in Pennylane: invoice id 123456 (customer id 7890).
```

## Prérequis

| | Versions prises en charge |
|---|---|
| PHP | 8.2 à 8.4 |
| Sylius | 2.2.9+ et 2.3 |
| Symfony | 6.4, 7.4 |
| `tmonier/sylius-einvoice-transmission-plugin` | ^0.2 |
| Base de données | MySQL 8.4 (celle de la CI ; le plugin n'a pas de table propre) |

Le plugin de transmission exige lui-même `tmonier/sylius-facturx-plugin`. Il faut un compte Pennylane avec accès à l'API et un jeton.

Le plugin est en version 0.x : sa configuration peut évoluer entre versions mineures, lisez le [CHANGELOG](CHANGELOG.md) avant de mettre à jour.

## Installation

1. Installez et configurez d'abord le plugin de transmission (voir son README), puis :

    ```bash
    composer require tmonier/sylius-einvoice-pennylane-plugin
    ```

2. Enregistrez le bundle dans `config/bundles.php` :

    ```php
    Tmonier\SyliusEinvoicePennylanePlugin\TmonierSyliusEinvoicePennylanePlugin::class => ['all' => true],
    ```

3. Placez le jeton dans l'environnement, jamais dans le code (`.env.local`, ou les secrets de votre hébergeur) :

    ```dotenv
    PENNYLANE_API_TOKEN=...
    ```

4. Configurez l'adaptateur (`config/packages/tmonier_einvoice_pennylane.yaml`) :

    ```yaml
    tmonier_sylius_einvoice_transmission:
        transmitter: Tmonier\SyliusEinvoicePennylanePlugin\Transmission\PennylaneInvoiceTransmitter
        # facultatif, permet de relancer un envoi en erreur (voir le plugin de transmission)
        document_provider: App\Einvoice\MyInvoiceDocuments

    tmonier_einvoice_pennylane:
        api_token: '%env(PENNYLANE_API_TOKEN)%'
    ```

Il n'y a ni migration, ni route, ni gabarit : le plugin n'ajoute que des services.

## Configuration

| Clé (`tmonier_einvoice_pennylane`) | Défaut | Rôle |
|---|---|---|
| `api_token` | `null` | Le jeton, sous la forme `%env(PENNYLANE_API_TOKEN)%`. Sans lui, tout envoi se termine en `error`. |
| `base_url` | `https://app.pennylane.com` | Hôte de l'API. À changer uniquement dans la configuration (par exemple pour le bac à sable), jamais depuis une saisie utilisateur. |
| `timeout` | `10.0` | Secondes d'attente d'une réponse, par requête (minimum 1). |
| `http_client` | `http_client` | Identifiant du service client HTTP Symfony utilisé pour les appels. |

## Limites

- Ni reprise automatique, ni file d'attente : une tentative par envoi, relancée explicitement.
- Pas d'écran d'administration : le résultat se lit dans le journal du plugin de transmission (table `tmonier_einvoice_transmission`, statut et message de chaque facture).
- Le statut ultérieur de la facture dans Pennylane (acceptation, paiement, cycle de vie) n'est pas relu.
- Le client Pennylane est créé à partir de la facture et jamais mis à jour ; les avoirs et les autres documents ne sont pas gérés.
- Les tests n'appellent jamais Pennylane : le client HTTP est scripté.

## Développement

```bash
composer install
docker compose up -d --wait
vendor/bin/phpunit
vendor/bin/ecs check
vendor/bin/phpstan analyse
```

## Auteur

[Thibaut Monier](https://www.linkedin.com/in/thibautmonier/) – Delivery Manager IA / Tech Lead

## Licence

MIT, voir [LICENSE](LICENSE).
