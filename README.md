# Laravel TOTP

> Package de double authentification TOTP (RFC 6238) pour Laravel avec support polymorphique

[![Latest Version](https://img.shields.io/packagist/v/andydefer/laravel-totp.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-totp)
[![Total Downloads](https://img.shields.io/packagist/dt/andydefer/laravel-totp.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-totp)
[![PHP Version](https://img.shields.io/packagist/php-v/andydefer/laravel-totp.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-totp)
[![License](https://img.shields.io/packagist/l/andydefer/laravel-totp.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-totp)

Un package Laravel pour la double authentification (2FA) avec TOTP (RFC 6238), support polymorphique, codes de récupération et génération de QR Code.

---

## 📋 Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Structure du package](#structure-du-package)
- [Utilisation](#utilisation)
  - [Configurer TOTP](#configurer-totp)
  - [Activer TOTP après vérification](#activer-totp-après-vérification)
  - [Vérifier un code TOTP](#vérifier-un-code-totp)
  - [Vérifier un code de récupération](#vérifier-un-code-de-récupération)
  - [Désactiver TOTP](#désactiver-totp)
  - [Régénérer les codes de récupération](#régénérer-les-codes-de-récupération)
  - [Vérifier l'état de TOTP](#vérifier-létat-de-totp)
- [Référence de l'API](#référence-de-lapi)
  - [TotpService](#totpservice)
  - [TotpGenerator](#totpgenerator)
  - [QrCodeGenerator](#qrcodegenerator)
- [Value Objects](#value-objects)
- [Structure de la base de données](#structure-de-la-base-de-données)
- [Workflow complet](#workflow-complet)
- [Tests](#tests)
- [Journal des modifications](#journal-des-modifications)
- [Contribuer](#contribuer)
- [Licence](#licence)

---

## ✨ Fonctionnalités

- ✅ **Polymorphisme** - Attachez TOTP à n'importe quel modèle (User, Admin, etc.) **sans que le modèle n'ait à déclarer de relation**
- ✅ **TOTP Standard** - RFC 6238 compatible (SHA1, 6 chiffres, période de 30s)
- ✅ **Génération de secrets** - Secrets Base32 (32 caractères)
- ✅ **Génération de QR Code** - Compatible Google Authenticator, Authy, Microsoft Authenticator
- ✅ **Codes de récupération** - 10 codes de secours de 8 caractères (hashés en SHA256)
- ✅ **Fenêtre de tolérance** - Tolérance de +/- 1 période (30s) pour les latences réseau
- ✅ **Activation/Désactivation** - Gestion complète du cycle de vie TOTP
- ✅ **Vérification** - Vérification des codes avec comparaison sécurisée (`hash_equals`)
- ✅ **Support des métadonnées** - Stockez des données supplémentaires au format JSON
- ✅ **Suppression douce** - SoftDeletes pour une suppression sécurisée
- ✅ **Tests complets** - Couverture complète des tests d'intégration (27 tests)

---

## 🚀 Prérequis

- PHP 8.2 ou supérieur
- Laravel 12.0, 13.0, 14.0 ou 15.0
- Extension GD (pour la génération de QR Code)

---

## 📦 Installation

Installez le package via Composer :

```bash
composer require andydefer/laravel-totp
```

### Publier les migrations

```bash
php artisan vendor:publish --tag=Totp-migrations
```

### Exécuter les migrations

```bash
php artisan migrate
```

---

## ⚙️ Configuration

Le package est automatiquement découvert par Laravel. Aucune configuration supplémentaire n'est requise.

Si vous devez personnaliser le Service Provider, ajoutez-le manuellement dans `config/app.php` :

```php
'providers' => [
    // ...
    AndyDefer\LaravelTotp\TotpServiceProvider::class,
],
```

### Dépendances optionnelles

Pour la génération de QR Code, le package utilise `endroid/qr-code`. Assurez-vous que l'extension GD est activée :

```bash
# Vérifier que GD est installé
php -m | grep gd

# Sur Ubuntu/Debian
sudo apt-get install php-gd

# Sur macOS avec Homebrew
brew install php-gd
```

---

## 🏗️ Structure du package

```
laravel-totp/
├── src/
│   ├── TotpServiceProvider.php
│   ├── Models/
│   │   └── TotpSecret.php          # Seul modèle avec relations polymorphiques
│   ├── Services/
│   │   ├── TotpService.php         # Service principal
│   │   ├── TotpGenerator.php       # Génération TOTP
│   │   └── QrCodeGenerator.php     # Génération QR Code
│   ├── ValueObjects/
│   │   └── TotpSecretVO.php
│   └── Exceptions/
│       └── TotpException.php
├── database/
│   └── migrations/
│       └── create_totp_secrets_table.php
└── tests/
    ├── Integration/
    │   └── Services/
    │       └── TotpServiceIntegrationTest.php
    ├── Fixtures/
    └── IntegrationTestCase.php
```

### ⚠️ Important : Aucune relation dans les modèles consommateurs

Contrairement à d'autres packages, **le modèle consommateur (User, Admin, etc.) n'a pas besoin de déclarer de relation**. Seul le modèle `TotpSecret` contient les relations polymorphiques.

```php
// ✅ Le modèle User reste propre - AUCUNE RELATION !
class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];
    // AUCUNE méthode totpSecret() ici !
}
```

---

## 📖 Utilisation

### Configurer TOTP

```php
use AndyDefer\LaravelTotp\Services\TotpService;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TotpService $totpService,
    ) {}

    public function setup(Request $request)
    {
        $user = $request->user();

        $setup = $this->totpService->setup($user);

        return response()->json([
            'secret' => $setup['secret'],
            'qr_code' => base64_encode($setup['qr_code']),
            'qr_code_uri' => $setup['qr_code_uri'],
            'recovery_codes' => $setup['recovery_codes'],
        ]);
    }
}
```

**Réponse :**
```json
{
    "secret": "JBSWY3DPEHPK3PXP",
    "qr_code": "iVBORw0KGgoAAAANSUhEUgAA...",
    "qr_code_uri": "otpauth://totp/Laravel:john@example.com?secret=JBSWY3DPEHPK3PXP&issuer=Laravel&algorithm=SHA1&digits=6&period=30",
    "recovery_codes": [
        "X7K9M2P4", "R3T8W6Q1", "F5N2V9L3",
        "C1H7A4E8", "Y6B0U3S9", "D2J5R8M1",
        "W4P7K0T6", "G9E3F1V8", "L2N5Q7C4", "S8U1B6X9"
    ]
}
```

### Activer TOTP après vérification

```php
public function enable(Request $request)
{
    $user = $request->user();

    $request->validate([
        'code' => 'required|string|size:6',
    ]);

    try {
        $enabled = $this->totpService->verifyAndEnable(
            authenticatable: $user,
            code: $request->code,
            window: 1, // Tolérance de +/- 1 période (30s)
        );

        if ($enabled) {
            return response()->json([
                'message' => 'TOTP enabled successfully',
                'verified_at' => now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'message' => 'Invalid TOTP code',
        ], 400);
    } catch (TotpException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 400);
    }
}
```

### Vérifier un code TOTP

```php
public function verifyLogin(Request $request)
{
    $user = User::where('email', $request->email)->firstOrFail();

    $request->validate([
        'code' => 'required|string|size:6',
    ]);

    try {
        $valid = $this->totpService->verify(
            authenticatable: $user,
            code: $request->code,
            window: 1,
        );

        if ($valid) {
            auth()->login($user);
            
            return response()->json([
                'message' => 'Login successful',
            ]);
        }

        return response()->json([
            'message' => 'Invalid TOTP code',
        ], 400);
    } catch (TotpException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 400);
    }
}
```

### Vérifier un code de récupération

```php
public function recover(Request $request)
{
    $user = User::where('email', $request->email)->firstOrFail();

    $request->validate([
        'recovery_code' => 'required|string',
    ]);

    try {
        $valid = $this->totpService->verifyRecoveryCode(
            authenticatable: $user,
            code: $request->recovery_code,
        );

        if ($valid) {
            auth()->login($user);
            
            return response()->json([
                'message' => 'Recovery successful',
            ]);
        }

        return response()->json([
            'message' => 'Invalid recovery code',
        ], 400);
    } catch (TotpException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 400);
    }
}
```

### Désactiver TOTP

```php
public function disable(Request $request)
{
    $user = $request->user();

    try {
        $this->totpService->disable($user);

        return response()->json([
            'message' => 'TOTP disabled successfully',
        ]);
    } catch (TotpException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 400);
    }
}
```

### Régénérer les codes de récupération

```php
public function regenerateRecoveryCodes(Request $request)
{
    $user = $request->user();

    try {
        $recoveryCodes = $this->totpService->regenerateRecoveryCodes($user);

        return response()->json([
            'recovery_codes' => $recoveryCodes->toArray(),
        ]);
    } catch (TotpException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 400);
    }
}
```

### Vérifier l'état de TOTP

```php
public function status(Request $request)
{
    $user = $request->user();

    return response()->json([
        'enabled' => $this->totpService->isEnabled($user),
        'verified' => $this->totpService->isVerified($user),
        'remaining_recovery_codes' => count(
            $this->totpService->getRemainingRecoveryCodes($user)
        ),
    ]);
}
```

---

## 📚 Référence de l'API

### TotpService

| Méthode | Description | Retourne | Exception |
|---------|-------------|----------|-----------|
| `setup(Model $authenticatable)` | Configurer TOTP (génère secret, QR Code, codes récupération) | `array{secret, qr_code, qr_code_uri, recovery_codes}` | - |
| `verifyAndEnable(Model $authenticatable, string $code, int $window = 1)` | Vérifier le code et activer TOTP | `bool` | `TotpException` |
| `enable(Model $authenticatable, string $secret, array $recoveryCodes)` | Activer TOTP avec un secret existant | `TotpSecret` | - |
| `disable(Model $authenticatable)` | Désactiver TOTP | `bool` | `TotpException` |
| `verify(Model $authenticatable, string $code, int $window = 1)` | Vérifier un code TOTP | `bool` | `TotpException` |
| `verifyRecoveryCode(Model $authenticatable, string $code)` | Vérifier un code de récupération | `bool` | `TotpException` |
| `markAsVerified(Model $authenticatable)` | Marquer TOTP comme vérifié | `void` | - |
| `isEnabled(Model $authenticatable)` | Vérifier si TOTP est activé | `bool` | - |
| `isVerified(Model $authenticatable)` | Vérifier si TOTP est vérifié | `bool` | - |
| `getRemainingRecoveryCodes(Model $authenticatable)` | Récupérer les codes de récupération restants | `StringTypedCollection` | - |
| `regenerateRecoveryCodes(Model $authenticatable)` | Régénérer les codes de récupération | `StringTypedCollection` | - |
| `getSecret(Model $authenticatable)` | Récupérer le secret | `?string` | - |

### TotpGenerator

| Méthode | Description | Retourne |
|---------|-------------|----------|
| `generateSecret(int $length = 32)` | Générer un secret Base32 | `string` |
| `generateCode(string $secret, ?int $timestamp = null, int $digits = 6, int $period = 30)` | Générer un code TOTP | `string` |
| `generateCodesWithWindow(string $secret, int $digits = 6, int $period = 30, int $window = 1, ?int $timestamp = null)` | Générer les codes avec fenêtre de tolérance | `array` |
| `generateRecoveryCodes(int $count = 10, int $length = 8)` | Générer des codes de récupération | `array` |
| `hashRecoveryCodes(array $codes)` | Hasher les codes de récupération (SHA256) | `array` |
| `verifyRecoveryCode(string $code, array $hashedCodes)` | Vérifier un code de récupération | `bool` |

### QrCodeGenerator

| Méthode | Description | Retourne |
|---------|-------------|----------|
| `generate(string $account, string $secret, string $issuer, int $digits = 6, int $period = 30, string $algorithm = 'SHA1')` | Générer un QR Code PNG | `string` |
| `generateFromUri(string $uri)` | Générer un QR Code à partir d'une URI | `string` |
| `generateDataUri(string $uri)` | Générer une Data URI du QR Code | `string` |
| `buildUri(string $account, string $secret, string $issuer, int $digits = 6, int $period = 30, string $algorithm = 'SHA1')` | Construire l'URI TOTP | `string` |

---

## 🎯 Value Objects

Le package supporte les Value Objects suivants :

| Value Object | Description | Exemple |
|--------------|-------------|---------|
| `TotpSecretVO` | Secret TOTP avec métadonnées | `new TotpSecretVO('JBSWY3...', true, [...], $verifiedAt)` |
| `DateTimeVO` | Date/heure | `new DateTimeVO('2024-01-01 12:00:00')` |
| `StrictDataObject` | Métadonnées typées | `StrictDataObject::from(['ip' => '127.0.0.1'])` |

### Accesseurs dans le modèle TotpSecret

```php
$totpSecret = TotpSecret::find(1);

// Accès sous forme de Value Objects
$secretVO = $totpSecret->getSecret();         // TotpSecretVO
$recoveryCodes = $totpSecret->getRecoveryCodes(); // array<string>
$verifiedAt = $totpSecret->getVerifiedAt();   // DateTimeVO
$createdAt = $totpSecret->getCreatedAt();     // DateTimeVO
$updatedAt = $totpSecret->getUpdatedAt();     // DateTimeVO
$metadata = $totpSecret->getMetadata();       // StrictDataObject

// Méthodes utilitaires
$totpSecret->isEnabled();     // bool
$totpSecret->isVerified();    // bool
$totpSecret->countRecoveryCodes();  // int
$totpSecret->hasRecoveryCodes();    // bool

// Relation polymorphique
$authenticatable = $totpSecret->authenticatable;  // User, Admin, etc.
```

---

## 📝 Structure de la base de données

```sql
CREATE TABLE totp_secrets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    authenticatable_type VARCHAR(255) NOT NULL,  -- Type de l'identifiant (morph)
    authenticatable_id BIGINT UNSIGNED NOT NULL, -- ID de l'identifiant
    secret VARCHAR(255) NOT NULL UNIQUE,         -- Secret Base32
    is_enabled BOOLEAN DEFAULT FALSE,            -- TOTP activé ?
    recovery_codes JSON NULL,                    -- Codes de récupération hashés
    verified_at TIMESTAMP NULL,                  -- Date de première vérification
    metadata JSON NULL,                          -- Métadonnées
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_authenticatable (authenticatable_type, authenticatable_id),
    INDEX idx_secret (secret),
    INDEX idx_is_enabled (is_enabled)
);
```

### Structure du champ `recovery_codes` (JSON)

```json
[
    "5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8",
    "b109f3bbbc244eb82441917ed06d618b9008dd09b3befd1b5e07394c706a8bb1",
    "..."
]
```

Les codes de récupération sont hashés en SHA256 avant stockage.

---

## 🔧 Exceptions possibles

| Exception | Message | Quand ? |
|-----------|---------|---------|
| `TotpException::totpNotEnabled()` | TOTP is not enabled for this user. | `verify()` appelé mais TOTP pas activé |
| `TotpException::secretNotFound()` | TOTP secret not found. | `verify()` appelé mais secret inexistant |
| `TotpException::invalidCode()` | Invalid TOTP code. | Code invalide |
| `TotpException::invalidRecoveryCode()` | Invalid recovery code. | Code de récupération invalide |
| `TotpException::alreadyEnabled()` | TOTP is already enabled for this user. | Activation déjà faite |
| `TotpException::alreadyDisabled()` | TOTP is already disabled for this user. | Désactivation déjà faite |
| `TotpException::maxAttemptsExceeded()` | Maximum TOTP attempts exceeded. | Trop de tentatives |
| `TotpException::setupFailed()` | TOTP setup failed. | Échec de la configuration |

---

## 👨‍💻 Auteur

**Andy Kani**
- GitHub: [@andydefer](https://github.com/andydefer)
- Email: andykanidimbu@gmail.com

---

## ⭐ Support

Si vous trouvez ce package utile, n'hésitez pas à lui donner une ⭐ sur GitHub !

---

## 🙏 Remerciements

- Framework Laravel
- Tous les contributeurs et utilisateurs de ce package

---

**Construit avec ❤️ pour la communauté Laravel**
---