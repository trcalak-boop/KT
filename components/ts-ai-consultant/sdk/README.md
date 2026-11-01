# SDK pro hosting bez composeru

Brána používá oficiální Anthropic PHP SDK (`anthropic-ai/sdk`, Apache-2.0) a Guzzle (MIT). Na hosting se nahrává
sestavená složka `vendor/` **mimo webroot** (např. `/private/ai-test/vendor/`), cesta se zadá v `sdkAutoload`.

Sestavení (lokálně, PHP 8.1+, composer.phar s ověřeným kontrolním součtem):

    cd <prázdná složka> && cp …/sdk/composer.json …/sdk/composer.lock .
    php composer.phar install --no-dev --no-plugins --no-scripts --optimize-autoloader

`composer.lock` určuje přesné verze (SDK 1.3.0, Guzzle 7.16.1, …). Licence všech balíčků: Apache-2.0 nebo MIT –
jejich texty jsou v `vendor/<balíček>/LICENSE` a musí se nahrát spolu s kódem.
