<?php

declare(strict_types=1);

use mojo\observer\forms\FormAdapter;
use mojo\observer\forms\FormAdapters;
use mojo\observer\forms\RegisterFormAdaptersEvent;
use mojo\observer\Observer;
use mojo\observer\providers\CraftlyDoctorProvider;
use mojo\observer\testing\TestToken;
use PHPUnit\Framework\TestCase;
use yii\base\Event;

/**
 * Forms reach observer through adapters that listeners register; only the available ones count.
 * A site without craftly has no doctor.
 */
final class ObserverFormAdaptersTest extends TestCase
{
    protected function tearDown(): void
    {
        Event::off(Observer::class, Observer::EVENT_REGISTER_FORM_ADAPTERS);
    }

    private static function adapter(string $handle, bool $available): FormAdapter
    {
        return new class($handle, $available) implements FormAdapter {
            public function __construct(private string $handle, private bool $available)
            {
            }

            public function handle(): string
            {
                return $this->handle;
            }

            public function available(): bool
            {
                return $this->available;
            }

            public function inventory(): array
            {
                return ['forms' => [['handle' => $this->handle . 'Contact']]];
            }

            public function listen(): void
            {
            }

            public function startTest(TestToken $token): void
            {
            }

            public function refuseTests(string $reason): void
            {
            }

            public function report(string $run): ?array
            {
                return null;
            }

            public function cleanUp(string $run): array
            {
                return ['deleted' => false, 'reason' => 'not found'];
            }
        };
    }

    public function testOnlyAvailableAdaptersAreListedUnderTheirHandle(): void
    {
        Event::on(Observer::class, Observer::EVENT_REGISTER_FORM_ADAPTERS, static function (RegisterFormAdaptersEvent $event): void {
            $event->adapters[] = self::adapter('formie', true);
            $event->adapters[] = self::adapter('freeform', false);
        });

        $all = FormAdapters::all();

        self::assertSame(['formie'], array_keys($all));
        self::assertSame([['handle' => 'formieContact']], FormAdapters::get('formie')?->inventory()['forms']);
        self::assertNull(FormAdapters::get('freeform'));
        self::assertNull(FormAdapters::get('nope'));
    }

    public function testWithoutListenersThereAreNoAdapters(): void
    {
        self::assertSame([], FormAdapters::all());
    }

    public function testTheDoctorIsMissingWithoutCraftly(): void
    {
        self::assertFalse((new CraftlyDoctorProvider('modules\\craftly\\health\\NoSuchDoctor'))->available());
    }
}
