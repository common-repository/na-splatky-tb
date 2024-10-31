=== Na splátkyTB ===
Contributors: webikon, kravco
Tags: payments, woocommerce, tatrabanka, nasplatky
Donate link: https://platobnebrany.sk/
Requires at least: 5.0
Tested up to: 5.9
Requires PHP: 7.0
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin Tatra banka Na splátky Vám umožní zobraziť možnosti nákupu na splátky formou ďalšej platobnej metódy vo Vašom WooCommerce e-shope.

== Description ==
Zvýšte obrat vášho e-shopu vďaka možnosti pre zákazníkov zaplatiť za tovary a služby viazaným spotrebiteľským úverom od Tatra banky.

Vaši zákazníci už nebudú musieť odkladať nákup drahších tovarov na neskôr.

Plugin Tatra banka Na splátky Vám umožní zobraziť možnosti nákupu na splátky formou ďalšej platobnej metódy vo Vašom WooCommerce e-shope.

Plugin zároveň pridáva k produktom možnosť vypočítať si výšku splátok pre rôzne obdobia splácania.

Viac informácií o službe Na splátky nájdete na https://www.tatrabanka.sk/sk/business/ucty-platby/prijimanie-platieb/na-splatky/

== Installation ==
1. Dohodnite si podmienky používania služby Na splátky v Tatrabanke a podpíšte s nimi zlmuvu
2. Stiahnite si plugin z WordPress.org
3. Nainštalujte a aktivujte si plugin
4. Na pripojenie Pluginu je potrebné zaregistrovať sa na developer.tatrabanka.sk, kde si vygenerujete API kľúč a aplikačné heslo
5. Do nastavení pluginu (WooCommerce - Nastavenia - Platby) zadajte API kľúč a aplikačné heslo
6. Vytvorte testovaciu objednávku a otestujte, či presmerovanie na portál Tatra banky prebehne úspešne

== Screenshots ==
1. Logo služby TB NaSplátky
2. Nastavenia pluginu
3. Zobrazenie tlačidla TB NaSplátky pri Woocommerce tlačidle  "Pridať do košíka"
4. Prehľad možností splátok
5. Zobrazenie platobnej brány v košíku Woocommerce

== Changelog ==
= 1.0.7 =
* Fix retrieving the total price of an order item if it is a coupon. 
* Prevent change of order status if it is already in one of the final states.

= 1.0.6 =
* Fix translations, rename plugin

= 1.0.5 =
* Fix loan amount to be float

= 1.0.4 =
* Fix for the allowed characters and logo.

= 1.0.3 =
* Change plugin name, textdomain and slug to na-splatky-tb. Add sandbox mode.

= 1.0.2 =
* Added Readme.txt and updated plugin comments.

= 1.0.1 =
* Updated to the latest version.

= 1.0.0 =
* First implementation.
