<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Automattic\WooCommerce\EmailEditor\Engine\PersonalizationTags\Personalization_Tag;
use Automattic\WooCommerce\EmailEditor\Engine\PersonalizationTags\Personalization_Tags_Registry;
use PersianKit\Modules\WooCommerce\EmailEditorTags;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class EmailEditorTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->name() !== 'test_nothing_happens_without_the_email_editor') {
            require_once dirname(__DIR__) . '/Support/email-editor-stubs.php';
        }
    }

    public function test_only_matching_tags_are_wrapped(): void
    {
        $registry = $this->registry();

        EmailEditorTags::wrap(
            $registry,
            static fn (string $token): bool => $token === '[woocommerce/order-number]',
            static fn (callable $original, mixed $context, mixed $args): string => '«' . $original($context, $args) . '»'
        );

        $this->assertSame('«109»', $registry->get_by_token('[woocommerce/order-number]')->execute_callback([]));
        $this->assertSame('Shop', $registry->get_by_token('[woocommerce/site-title]')->execute_callback([]));
        $this->assertSame('220000.00', $registry->get_by_token('[woocommerce/order-total]')->execute_callback([]));
    }

    public function test_the_registry_keeps_its_order(): void
    {
        $registry = $this->registry();

        EmailEditorTags::wrap($registry, static fn (string $token): bool => $token === '[woocommerce/order-number]', static fn (callable $original): string => '');

        $this->assertSame(
            ['[woocommerce/site-title]', '[woocommerce/order-number]', '[woocommerce/order-total]'],
            array_keys($registry->get_all())
        );
    }

    public function test_the_copy_keeps_everything_but_the_callback(): void
    {
        $registry = new Personalization_Tags_Registry();
        $registry->register(new Personalization_Tag(
            'Order Date',
            'woocommerce/order-date',
            'Order',
            static fn (): string => 'date',
            ['format' => 'F j, Y'],
            '[woocommerce/order-date format="F j, Y"]',
            ['woo_email']
        ));

        EmailEditorTags::wrap($registry, [EmailEditorTags::class, 'isOrderTag'], static fn (callable $original): string => 'wrapped');

        $tag = $registry->get_by_token('[woocommerce/order-date]');
        $this->assertSame('Order Date', $tag->get_name());
        $this->assertSame('[woocommerce/order-date]', $tag->get_token());
        $this->assertSame('Order', $tag->get_category());
        $this->assertSame(['format' => 'F j, Y'], $tag->get_attributes());
        $this->assertSame('[woocommerce/order-date format="F j, Y"]', $tag->get_value_to_insert());
        $this->assertSame(['woo_email'], $tag->get_post_types());
        $this->assertSame('wrapped', $tag->execute_callback([]));
    }

    public function test_the_wrapper_gets_the_original_callback_context_and_args(): void
    {
        $registry = new Personalization_Tags_Registry();
        $registry->register(new Personalization_Tag(
            'Order Date',
            'woocommerce/order-date',
            'Order',
            static fn (array $context, array $args = []): string => $context['order'] . '|' . ($args['format'] ?? '')
        ));
        $seen = null;

        EmailEditorTags::wrap($registry, [EmailEditorTags::class, 'isOrderTag'], function (callable $original, mixed $context, mixed $args) use (&$seen): string {
            $seen = [$context, $args];

            return $original($context, $args);
        });

        $value = $registry->get_by_token('[woocommerce/order-date]')->execute_callback(['order' => 109], ['format' => 'Y']);

        $this->assertSame('109|Y', $value);
        $this->assertSame([['order' => 109], ['format' => 'Y']], $seen);
    }

    public function test_wraps_nest(): void
    {
        $registry = $this->registry();
        $isNumber = static fn (string $token): bool => $token === '[woocommerce/order-number]';

        EmailEditorTags::wrap($registry, $isNumber, static fn (callable $original, mixed $context, mixed $args): string => '(' . $original($context, $args) . ')');
        EmailEditorTags::wrap($registry, $isNumber, static fn (callable $original, mixed $context, mixed $args): string => '[' . $original($context, $args) . ']');

        $this->assertSame('[(109)]', $registry->get_by_token('[woocommerce/order-number]')->execute_callback([]));
    }

    public function test_values_other_than_a_registry_pass_through(): void
    {
        $wrapper = static fn (callable $original): string => '';

        $this->assertNull(EmailEditorTags::wrap(null, [EmailEditorTags::class, 'isOrderTag'], $wrapper));
        $this->assertSame('registry', EmailEditorTags::wrap('registry', [EmailEditorTags::class, 'isOrderTag'], $wrapper));

        $object = new \stdClass();
        $this->assertSame($object, EmailEditorTags::wrap($object, [EmailEditorTags::class, 'isOrderTag'], $wrapper));
    }

    public function test_order_tags_are_the_woocommerce_order_ones(): void
    {
        $this->assertTrue(EmailEditorTags::isOrderTag('[woocommerce/order-number]'));
        $this->assertTrue(EmailEditorTags::isOrderTag('[woocommerce/order-date]'));
        $this->assertFalse(EmailEditorTags::isOrderTag('[woocommerce/site-title]'));
        $this->assertFalse(EmailEditorTags::isOrderTag('[woocommerce/customer-first-name]'));
        $this->assertFalse(EmailEditorTags::isOrderTag('[mailpoet/order-number]'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_nothing_happens_without_the_email_editor(): void
    {
        $this->assertFalse(class_exists(Personalization_Tag::class));

        $registry = new class {
            public int $calls = 0;

            /** @return array<mixed> */
            public function get_all(): array
            {
                $this->calls++;

                return [];
            }

            public function unregister(mixed $tag): void
            {
            }

            public function register(mixed $tag): void
            {
            }
        };

        $this->assertSame($registry, EmailEditorTags::wrap($registry, [EmailEditorTags::class, 'isOrderTag'], static fn (callable $original): string => ''));
        $this->assertSame(0, $registry->calls);
    }

    private function registry(): Personalization_Tags_Registry
    {
        $registry = new Personalization_Tags_Registry();
        $registry->register(new Personalization_Tag('Site Title', 'woocommerce/site-title', 'Site', static fn (): string => 'Shop'));
        $registry->register(new Personalization_Tag('Order Number', 'woocommerce/order-number', 'Order', static fn (): string => '109'));
        $registry->register(new Personalization_Tag('Order Total', 'woocommerce/order-total', 'Order', static fn (): string => '220000.00'));

        return $registry;
    }
}
