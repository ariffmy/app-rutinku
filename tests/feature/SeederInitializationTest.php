<?php

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Database\Seeds\ProductionSeeder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/** Seeder tests always use a new, private in-memory SQLite database, never .env DB credentials. */
final class SeederInitializationTest extends CIUnitTestCase
{
    private BaseConnection $seedDb;
    private array $savedEnvironment = [];

    private const SETTINGS = [
        'CI_ENVIRONMENT' => 'testing',
        'RUTINKU_FAMILY_NAME' => 'Fixture Family',
        'RUTINKU_PARENT1_NAME' => 'Fixture Parent One',
        'RUTINKU_PARENT1_EMAIL' => 'first@fixture.example',
        'RUTINKU_PARENT1_PASSWORD' => 'Fixture-only-secret-one!',
        'RUTINKU_PARENT2_NAME' => 'Fixture Parent Two',
        'RUTINKU_PARENT2_EMAIL' => 'second@fixture.example',
        'RUTINKU_PARENT2_PASSWORD' => 'Fixture-only-secret-two!',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::SETTINGS as $key => $value) {
            $this->savedEnvironment[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'process' => getenv($key),
            ];
            $this->setEnvironment($key, $value);
        }

        $this->seedDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => 'seed_test_',
            'DBDebug' => false,
            'foreignKeys' => true,
            'busyTimeout' => 1000,
        ], false);
        $forge = Database::forge($this->seedDb);

        // Only create schema in the disposable memory connection; no refresh/drop command.
        foreach (glob(APPPATH . 'Database/Migrations/*.php') as $path) {
            require_once $path;
            $name = preg_replace('/^\d{4}-\d{2}-\d{2}-\d{6}_/', '', pathinfo($path, PATHINFO_FILENAME));
            $class = 'App\\Database\\Migrations\\' . $name;
            (new $class($forge))->up();
        }
    }

    protected function tearDown(): void
    {
        $this->seedDb->close();
        foreach ($this->savedEnvironment as $key => $saved) {
            unset($_ENV[$key], $_SERVER[$key]);
            if ($saved['env'] !== null) {
                $_ENV[$key] = $saved['env'];
            }
            if ($saved['server'] !== null) {
                $_SERVER[$key] = $saved['server'];
            }
            putenv($saved['process'] === false ? $key : $key . '=' . $saved['process']);
        }

        parent::tearDown();
    }

    public function testProductionCreatesOneFamilyAndExactlyTwoHashedParents(): void
    {
        $this->runProduction();

        $this->assertSame(1, $this->countRows('families'));
        $this->assertSame(2, $this->countRows('users'));
        $this->assertSame(2, $this->seedDb->table('users')->where('role', 'parent')->countAllResults());
        $parents = $this->seedDb->table('users')->orderBy('id')->get()->getResultArray();
        foreach ($parents as $index => $parent) {
            $password = self::SETTINGS['RUTINKU_PARENT' . ($index + 1) . '_PASSWORD'];
            $this->assertTrue(password_verify($password, $parent['password_hash']));
            $this->assertNotSame($password, $parent['password_hash']);
            $this->assertSame(1, (int) $parent['is_active']);
        }

        $family = $this->seedDb->table('families')->get()->getRowArray();
        $this->assertSame('Fixture Family', $family['name']);
        $memberships = $this->seedDb->table('family_users')->get()->getResultArray();
        $this->assertCount(2, $memberships);
        foreach ($memberships as $membership) {
            $this->assertSame((int) $family['id'], (int) $membership['family_id']);
        }
    }

    public function testProductionCreatesNoChildrenProfilesOrOtherDemoData(): void
    {
        $this->runProduction();

        $this->assertSame(0, $this->seedDb->table('users')->where('role', 'child')->countAllResults());
        foreach (['child_profiles', 'user_devices', 'routines', 'routine_days', 'routine_tasks', 'task_completions', 'point_transactions', 'rewards', 'reward_redemptions', 'audit_logs'] as $table) {
            $this->assertSame(0, $this->countRows($table), $table . ' must remain empty.');
        }
    }

    public function testRerunPreservesUsersPasswordsFamilyAndMemberships(): void
    {
        $this->runProduction();
        $before = $this->snapshot();
        $this->setEnvironment('RUTINKU_PARENT1_NAME', 'Do not overwrite');
        $this->setEnvironment('RUTINKU_PARENT1_PASSWORD', 'Not-a-password-reset!');
        $this->runProduction();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(2, $this->countRows('users'));
        $this->assertSame(2, $this->countRows('family_users'));
    }

    public function testExistingParentWithoutMembershipIsAttachedWithoutReplacement(): void
    {
        $familyId = $this->fixtureFamily('Fixture Family');
        $parentId = $this->fixtureUser('first@fixture.example');
        $original = $this->seedDb->table('users')->where('id', $parentId)->get()->getRowArray();
        $this->runProduction();

        $this->assertSame($original, $this->seedDb->table('users')->where('id', $parentId)->get()->getRowArray());
        $this->assertSame(1, $this->countRows('families'));
        $this->assertSame(2, $this->countRows('users'));
        $this->assertSame(2, $this->seedDb->table('family_users')->where('family_id', $familyId)->countAllResults());
    }

    #[DataProvider('missingSettings')]
    public function testMissingOrBlankSettingFailsBeforeAnyInsert(string $key, ?string $value): void
    {
        $this->setEnvironment($key, $value);
        $this->assertProductionFails($key);
        $this->assertEmptyIdentityTables();
    }

    public static function missingSettings(): iterable
    {
        foreach (array_keys(self::SETTINGS) as $key) {
            if ($key !== 'CI_ENVIRONMENT') {
                yield $key . ' missing' => [$key, null];
                yield $key . ' blank' => [$key, ''];
                yield $key . ' whitespace' => [$key, '   '];
            }
        }
    }

    #[DataProvider('invalidEmails')]
    public function testInvalidEmailFailsBeforeAnyInsert(string $key): void
    {
        $this->setEnvironment($key, 'invalid-email');
        $this->assertProductionFails($key);
        $this->assertEmptyIdentityTables();
    }

    public static function invalidEmails(): array
    {
        return [['RUTINKU_PARENT1_EMAIL'], ['RUTINKU_PARENT2_EMAIL']];
    }

    public function testDuplicateEmailsAreRejectedAfterNormalization(): void
    {
        $this->setEnvironment('RUTINKU_PARENT2_EMAIL', ' FIRST@FIXTURE.EXAMPLE ');
        $this->assertProductionFails('must be different');
        $this->assertEmptyIdentityTables();
    }

    public function testNamesAndEmailsAreTrimmedButPasswordBytesArePreserved(): void
    {
        $this->setEnvironment('RUTINKU_FAMILY_NAME', ' Fixture Family ');
        $this->setEnvironment('RUTINKU_PARENT1_EMAIL', ' FIRST@FIXTURE.EXAMPLE ');
        $this->setEnvironment('RUTINKU_PARENT1_PASSWORD', '  Intentional password spaces  ');
        $this->runProduction();

        $parent = $this->seedDb->table('users')->where('email', 'first@fixture.example')->get()->getRowArray();
        $this->assertNotNull($parent);
        $this->assertTrue(password_verify('  Intentional password spaces  ', $parent['password_hash']));
    }

    public function testOverlongPasswordIsRejectedWithoutEchoingSecret(): void
    {
        $secret = str_repeat('private-value', 7);
        $this->setEnvironment('RUTINKU_PARENT2_PASSWORD', $secret);
        $this->assertProductionFails('RUTINKU_PARENT2_PASSWORD', $secret);
        $this->assertEmptyIdentityTables();
    }

    public function testDemoSeederIsBlockedInProductionBeforeAnyWrite(): void
    {
        $this->setEnvironment('CI_ENVIRONMENT', 'production');
        try {
            (new DemoSeeder(config(Database::class), $this->seedDb))->run();
            $this->fail('DemoSeeder must reject production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('disabled in production', $exception->getMessage());
        }
        $this->assertEmptyIdentityTables();
    }

    public function testProductionSeederAllowsProductionOnTheIsolatedTestConnection(): void
    {
        $this->setEnvironment('CI_ENVIRONMENT', 'production');
        $this->runProduction();
        $this->assertSame(2, $this->countRows('users'));
    }

    public function testDemoRerunDoesNotDuplicateOrResetExistingDemoData(): void
    {
        $demo = new DemoSeeder(config(Database::class), $this->seedDb);
        $demo->run();
        $before = $this->snapshot();
        $demo->run();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(1, $this->countRows('families'));
        $this->assertSame(5, $this->countRows('users'));
        $this->assertSame(5, $this->countRows('family_users'));
        $this->assertSame(3, $this->countRows('child_profiles'));
    }

    public function testDifferentExistingFamilyIsNotRenamedOrDuplicated(): void
    {
        $this->fixtureFamily('Existing Live Family');
        $before = $this->snapshot();
        $this->assertProductionFails('does not match');
        $this->assertSame($before, $this->snapshot());
    }

    public function testMultipleFamiliesAreRejectedWithoutGuessing(): void
    {
        $this->fixtureFamily('Fixture Family');
        $this->fixtureFamily('Fixture Family');
        $before = $this->snapshot();
        $this->assertProductionFails('multiple existing families');
        $this->assertSame($before, $this->snapshot());
    }

    public function testExistingUnconfiguredParentIsNotSilentlyReplaced(): void
    {
        $this->fixtureUser('unconfigured@fixture.example');
        $before = $this->snapshot();
        $this->assertProductionFails('not in the configured production pair');
        $this->assertSame($before, $this->snapshot());
    }

    public function testRoleConflictOnSecondParentRollsBackFirstParentAndFamily(): void
    {
        $this->fixtureUser('second@fixture.example', 'child');
        $before = $this->snapshot();
        $this->assertProductionFails('incompatible role');
        $this->assertSame($before, $this->snapshot());
    }

    public function testInactiveParentIsNotReactivated(): void
    {
        $this->fixtureUser('first@fixture.example', 'parent', false);
        $before = $this->snapshot();
        $this->assertProductionFails('inactive');
        $this->assertSame($before, $this->snapshot());
    }

    public function testDatabaseFailureOnSecondInsertRollsBackWithoutStaleId(): void
    {
        $this->seedDb->query("CREATE TRIGGER fail_second_parent BEFORE INSERT ON seed_test_users WHEN NEW.email = 'second@fixture.example' BEGIN SELECT RAISE(ABORT, 'forced fixture failure'); END");
        $this->assertProductionFails('could not insert a record into users');
        $this->assertEmptyIdentityTables();
    }

    public function testMembershipInsertFailureRollsBackFamilyAndUser(): void
    {
        $this->seedDb->query("CREATE TRIGGER fail_membership BEFORE INSERT ON seed_test_family_users BEGIN SELECT RAISE(ABORT, 'forced fixture failure'); END");
        $this->assertProductionFails('could not insert a record into family_users');
        $this->assertEmptyIdentityTables();
    }

    public function testDemoNeverAddsASecondFamilyMembershipToAnExistingUser(): void
    {
        $familyId = $this->fixtureFamily('Other Fixture Family');
        $parentId = $this->fixtureUser('parent1@example.com');
        $this->seedDb->table('family_users')->insert(['family_id' => $familyId, 'user_id' => $parentId]);
        $before = $this->snapshot();

        try {
            (new DemoSeeder(config(Database::class), $this->seedDb))->run();
            $this->fail('An existing membership must not be moved or duplicated.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('another family', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
    }

    private function runProduction(): void
    {
        (new ProductionSeeder(config(Database::class), $this->seedDb))->run();
    }

    private function assertProductionFails(string $message, ?string $secret = null): void
    {
        try {
            $this->runProduction();
            $this->fail('ProductionSeeder should reject this fixture.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
            if ($secret !== null) {
                $this->assertStringNotContainsString($secret, $exception->getMessage());
            }
        }
    }

    private function countRows(string $table): int
    {
        return $this->seedDb->table($table)->countAllResults();
    }

    private function assertEmptyIdentityTables(): void
    {
        foreach (['families', 'users', 'family_users', 'child_profiles'] as $table) {
            $this->assertSame(0, $this->countRows($table), $table . ' must not contain partial seed data.');
        }
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['families', 'users', 'family_users', 'child_profiles'] as $table) {
            $snapshot[$table] = $this->seedDb->table($table)->orderBy('id')->get()->getResultArray();
        }

        return $snapshot;
    }

    private function fixtureFamily(string $name): int
    {
        $this->seedDb->table('families')->insert(['name' => $name]);

        return (int) $this->seedDb->insertID();
    }

    private function fixtureUser(string $email, string $role = 'parent', bool $active = true): int
    {
        $this->seedDb->table('users')->insert([
            'name' => 'Existing Fixture', 'email' => $email,
            'password_hash' => password_hash('Existing-fixture-secret!', PASSWORD_DEFAULT),
            'role' => $role, 'is_active' => $active,
        ]);

        return (int) $this->seedDb->insertID();
    }

    private function setEnvironment(string $key, ?string $value): void
    {
        unset($_ENV[$key], $_SERVER[$key]);
        if ($value !== null) {
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        putenv($value === null ? $key : $key . '=' . $value);
    }
}
