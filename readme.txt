=== Craftgate Payment Gateway ===
Contributors: craftgateio
Tags: craftgate, payment gateway, ödeme geçidi, payment orchestration
Requires at least: 4.4
Tested up to: 6.4.3
Requires PHP: 5.6
Stable tag: 1.0.12
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Craftgate ödeme geçidini kullanarak WooCommerce üzerinden kolayca ödeme almanızı sağlayan teknik entegrasyon.

== Description ==
= Craftgate Nedir? =

Craftgate, işletmenizin online ödeme süreçlerini tek noktadan yönetebileceğiniz, tüm bankaların sanal POS’larını, birçok ödeme/e-para kuruluşunu, alternatif ve yurtdışı ödeme yöntemlerini sisteminize entegre etmenizi sağlayan bir ödeme geçididir.

= Hangi İşletmeler Craftgate Ödeme Geçidini Kullanabilir? =

Online ödeme alan ve en az bir sanal POS’a sahip olan her işletme, üyelik süreçlerini tamamlamalarının ardından, Craftgate’i kullanmaya başlayabilirler. 

= Neden Craftgate Ödeme Geçidini Kullanmalısınız? =

* Craftgate ödeme geçidi ile dilediğiniz banka, ödeme/e-para kuruluşu ile çalışma imkanı elde edersiniz. Ayrıca, alternatif ödeme yöntemlerini veya yurt dışı ödeme kuruluşlarını da sisteminize kolayca dahil edebilirsiniz. Bunun için, ilgili banka ve kuruluşlar ile anlaşma yapmış olmanız yeterlidir. 
* Craftgate ödeme geçidi ile tüm online ödeme süreçlerinizi tek merkezden yönetirsiniz. 
* Craftgate ödeme geçidinin kart saklama, kapalı devre cüzdan, limit birleştirme gibi birçok katma değerli servisinden yararlanarak, işletmenizin ödeme başarı oranlarını artırır, müşterilerinize akıcı bir ödeme deneyimi yaşatırsınız. 
* Craftgate’in 20’den fazla katma değerli servisi ile işletmenizin ödeme süreçlerinden kaynaklı giderlerini azaltır, ciro kayıplarını engellersiniz. 

= Craftgate Ödeme Geçidini Kullanmaya Nasıl Başlayabilirsiniz? =

WooCommerce eklentisi üzerinden Craftgate ödeme geçidini kullanmaya başlamak için öncelikle Craftgate üyelik süreçlerinizi tamamlamanız gerekir. 
Üyelik işleminizi tamamlamak için aşağıdaki adımları izleyebilirsiniz: 

1. [craftgate.io](https://craftgate.io) sayfamızdan "Hemen Başla" butonuna tıklayınız.
1. Kayıt formunda sizden istenilen bilgileri doldurup, işletmenize ait belgeleri sistemimize yükleyiniz. 
1. Tarafınıza gönderdiğimiz dijital sözleşmeyi onaylamanızın ardından, Craftgate üyeliğinizi tamamlamış olursunuz.

Üyelik onayınızdan sonra [buradaki](https://developer.craftgate.io/hazir-eticaret-modulleri/woocommerce) adımları takip ederek WooCommerce eklentisi üzerinden Craftgate ödeme geçidini sisteminize dahil edebilirsiniz.

= Ürün özellikleri =

* Ödeme sistemleri alanında 16 yılı aşan tecrübe ve bilgi birikiminin yansıdığı ödeme geçidi ürünü
* Banka sanal POS’ları, ödeme/e-para kuruluşları ile alternatif ödeme yöntemlerinin hazır teknik entegrasyonu
* PCI-DSS-1 uyumlu kart saklama özelliği, tek tıkla ödeme, link ve QR kod ile ödeme yöntemi, kapalı devre cüzdan gibi 20’den fazla katma değerli servis
* Pazaryeri, alt üye işyeri ve para dağıtma modeli desteği, ödeme formu, ortak ödeme sayfası gibi ödeme süreçlerini kolaylaştıran çözümler
* Ödeme hatalarında veya sanal POS kesintilerinde dahi 7/24 ödeme almayı sağlayan Akıllı ve Dinamik Ödeme Yönlendirme, Ödeme Tekrar Deneme ve Autopilot gibi katma değerli servisler 
* Gelişmiş ve kullanıcı dostu üye işyeri kontrol paneli
* 8 farklı para biriminde ödeme almayı destekleyen teknik altyapı, 
* Yurt dışından ödeme almak istenmesi halinde, anlaşma sağlanabilecek Stripe, Payoneer, AliPay, PayPal, Braintree, Afterpay ve Klarna gibi yurt dışı ödeme kuruluşlarına kolay teknik entegrasyon 
* Birçok programlama dilini kapsayan, kolay entegre edilebilir API ve geliştirici portalı
* Entegrasyon sürecinde veya daha sonra ihtiyaç duyulması halinde ürünü geliştiren mühendislerin kendisinden, hızlı ve etkin teknik destek imkanı


== Installation ==
* Craftgate WooCommerce eklentisini indirip Wordpress eklentiler sayfasından yükleyebilirsiniz. Ayrıca Wordpress eklenti arama sayfasına "craftgate" yazarak da eklentiyi yükleyebilirsiniz.
* Eklentiyi yüklendikten sonra Craftgate Payment Gateway eklentisini etkinleştiriniz.
* Etkinleştirdikten sonra Yönet butonuna tıklayarak yönetim sayfasına geliniz.
* Live API Key ve Live Secret Key girerek canlı ortamdan ödeme alabilirsiniz. Sandbox API Key ve Sandbox Secret Key girerek ise test ortamını kullanarak ödeme alabilirsiniz.
* Test ortamından ödeme almak için Enable Sandbox Mode seçeneğinin seçili olması gerekmektedir. Kapalı olduğu durumda canlı ortamdan ödeme alma aktif olacaktır.
* Detaylı bilgi için [WooCommerce eklenti](https://developer.craftgate.io/hazir-eticaret-modulleri/woocommerce) adresini ziyaret edebilirsiniz.

== Screenshots ==
1. Eklentiler
2. Ödeme Yöntemleri
3. Ayarlar
4. Sipariş Sayfası
5. Ödeme Formu
6. Sipariş Yönetim Sayfası

== Changelog ==
= 1.0.12 - 2024-03-20 =
* updates wordpress tags

= 1.0.11 - 2024-03-20 =
* adds woocommerce hpos support

= 1.0.10 - 2024-02-13 =
* adds woocommerce checkout blocks support

= 1.0.9 - 2023-01-02 =
* updates readme.txt

= 1.0.8 - 2022-12-26 =
* adds additional checks for nullable fields

= 1.0.7 - 2022-11-09 =
* updates wordpress and woocommerce versions

= 1.0.6 - 2022-07-08 =
* sends sum of items price in price field instead of sending order total

= 1.0.5 - 2022-04-20 =
* adds webhook feature to determine unhandled payments on client side

= 1.0.4 - 2022-02-28 =
* adds card storage functionality
* adds shipping total to payment items
* auto resize iframe container regarding to its  content
* add GBP currency

= 1.0.3 - 2022-02-15 =
* adds billing email address to init checkout form request

= 1.0.2 - 2022-01-25 =
* adds multi currency support
* adds checkout form language support
* adds iframe options to customize checkout form

= 1.0.1 - 2021-09-28 =
* same site cookie fixes
* adds installment fee to order after checkout

= 1.0.0 - 2021-04-02 =
* First Release

== Upgrade Notice ==
= 1.0.12 - 2024-03-20 =
* updates wordpress tags

= 1.0.11 - 2024-03-20 =
* adds woocommerce hpos support

= 1.0.10 - 2024-02-13 =
* adds woocommerce checkout blocks support

= 1.0.9 - 2023-01-02 =
* updates readme.txt

= 1.0.8 - 2022-12-26 =
* adds additional checks for nullable fields

= 1.0.7 - 2022-11-09 =
* updates wordpress and woocommerce versions

= 1.0.6 - 2022-07-08 =
* sends sum of items price in price field instead of sending order total

= 1.0.5 - 2022-04-20 =
* adds webhook feature to determine unhandled payments on client side

= 1.0.4 - 2022-02-28 =
* adds card storage functionality
* adds shipping total to payment items
* auto resize iframe container regarding to its  content
* add GBP currency

= 1.0.3 - 2022-02-15 =
* adds billing email address to init checkout form request

= 1.0.2 - 2022-01-25 =
* adds multi currency support
* adds checkout form language support
* adds iframe options to customize checkout form

= 1.0.1 - 2021-09-28 =
* same site cookie fixes
* adds installment fee to order after checkout

= 1.0.0 - 2021-04-02 =
* First Release
