<?php
/*
Plugin Name: SMSoffice.ge eCommerce სმს ის გამგზავნი
Description: ამ ჩანართით მომხმარებლებს შეუძლიათ მარტივად გაგზავნონ შეკვეთის სტატუსებთან დაკავშირებული ინფორმაციები ავტომატურად.
Version: 1.0
Author: Nika Sert
Author URI: https://github.com/nsroot
*/


// WordPress yüklenmiş mi kontrolü
if (file_exists(ABSPATH . 'wp-load.php')) {
    require_once(ABSPATH . 'wp-load.php');
} else {
    die('WordPress yüklü değil.');
}

// Ayarları tanımlama
function woosms_settings_init() {
    register_setting('woosms_settings', 'woosms_api_key');
    register_setting('woosms_settings', 'woosms_sender');
    register_setting('woosms_settings', 'woosms_enabled_actions');
}

// Ayar sayfasını oluşturma
function woosms_settings_page() {
    ?>
    <div class="wrap">
        <h1>SMSoffice.ge პარამეტრები</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('woosms_settings');
            do_settings_sections('woosms-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Ayar sayfasında görüntülenecek alanları tanımlama
function woosms_settings_fields() {
    add_settings_section('woosms_main_section', 'მთავარი პარამეტრები', '__return_false', 'woosms-settings');
    add_settings_field('woosms_api_key', 'API გასაღები', 'woosms_api_key_field', 'woosms-settings', 'woosms_main_section');
    add_settings_field('woosms_sender', 'გამგზავნის სათაური', 'woosms_sender_field', 'woosms-settings', 'woosms_main_section');

    // WooCommerce eylemleri seçenekleri
    $woocommerce_actions = array(
        'woocommerce_new_order' => 'ახალი შეკვეთა',
        'woocommerce_order_status_pending' => 'შეკვეთის სტატუსი: შეკვეთა მიღებულია',
        'woocommerce_order_status_processing' => 'შეკვეთის სტატუსი: მუშავდება',
        'woocommerce_order_status_completed' => 'შეკვეთის სტატუსი: დასრულებულია',
        // Diğer eylemleri buraya ekleyebilirsiniz
    );

    foreach ($woocommerce_actions as $action => $label) {
        add_settings_field(
            'woosms_action_' . $action,
            $label,
            'woosms_action_checkbox',
            'woosms-settings',
            'woosms_main_section',
            array('action' => $action, 'label' => $label)
        );
    }
}

// WooCommerce eylemleri için checkbox oluşturma
function woosms_action_checkbox($args) {
    $option_name = 'woosms_enabled_actions';
    $enabled_actions = get_option($option_name, array());

    $checked = in_array($args['action'], $enabled_actions) ? 'checked' : '';

    echo '<input type="checkbox" name="' . esc_attr($option_name) . '[]" value="' . esc_attr($args['action']) . '" ' . $checked . ' />';
}

// API Anahtarı alanını oluşturma
function woosms_api_key_field() {
    $api_key = get_option('woosms_api_key');
    echo '<input type="text" name="woosms_api_key" value="' . esc_attr($api_key) . '" />';
}

// Gönderen alanını oluşturma
function woosms_sender_field() {
    $sender = get_option('woosms_sender');
    echo '<input type="text" name="woosms_sender" value="' . esc_attr($sender) . '" />';
}

// Eklenti menüsü oluştur
function woosms_menu() {
    add_menu_page('WooSMS Confirmation', 'Smsoffice-Minitek', 'manage_options', 'woosms-settings', 'woosms_settings_page');
    add_submenu_page('woosms-settings', 'Log ჩანაწერები', 'ლოგ ჩანაწერები', 'manage_options', 'woosms-log', 'woosms_log_page');
}

// Loglama Fonksiyonu
function woosms_log($message) {
    $log_file = WP_CONTENT_DIR . '/woosms-log.txt';
    $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Log Sayfası
function woosms_log_page() {
    $log_file = WP_CONTENT_DIR . '/woosms-log.txt';
    if (file_exists($log_file)) {
        echo '<div class="wrap">';
        echo '<h1>WooSMS Log</h1>';
        echo '<pre>';
        echo esc_html(file_get_contents($log_file));
        echo '</pre>';
        echo '</div>';
    } else {
        echo '<div class="wrap">';
        echo '<h1>WooSMS Log</h1>';
        echo '<p>Log dosyası bulunamadı.</p>';
        echo '</div>';
    }
}

// WooCommerce eylemlerini dinleme
function woosms_listen_for_woocommerce_actions($order_id, $old_status, $new_status) {
    // Kontrol etmek için kullanılacak eylemleri getir
    $enabled_actions = get_option('woosms_enabled_actions', array());

    // Eğer bu eylem etkinleştirilmişse devam et
    if (in_array(current_filter(), $enabled_actions)) {
        $order = wc_get_order($order_id);

        // Müşteri telefon numarasını al
        $customer_mobile = $order->get_billing_phone();

        // API anahtarını ve gönderen bilgisini al
        $api_key = get_option('woosms_api_key');
        $sender = get_option('woosms_sender');

        // Sipariş numarasını ve web sitesi bağlantısını al
        $order_number = $order->get_order_number();
        $website_url = get_site_url();

        // SMS içeriği oluştur
        $sms_content = 'თქვენი შეკვეთა მიღებულია! შეკვეთის ნომერი: ' . $order_number . '.' . $website_url;

        $sms_url = 'https://smsoffice.ge/api/v2/send/?key=' . $api_key;

        $data = array(
            'destination' => $customer_mobile,
            'sender' => $sender,
            'content' => $sms_content,
            'urgent' => 'true'
        );

        $ch = curl_init($sms_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        // Hata kontrolü ve loglama
        if ($response === false) {
            $error_message = 'სმს ის გაგზავნის შეცდომა: ' . curl_error($ch);
            error_log($error_message);
            woosms_log($error_message);
        } else {
            $success_message = 'სმს ის გაგზავნის პასუხი: ' . $response;
            error_log($success_message);
            woosms_log($success_message);
        }

        curl_close($ch);
    }
}

// Ayarları tanımlama ve sayfa eylemlerini eklemek
add_action('admin_init', 'woosms_settings_init');
add_action('admin_menu', 'woosms_menu');
add_action('admin_init', 'woosms_settings_fields');

// WooCommerce eylemlerini dinleme fonksiyonunu belirli eylemlere bağlama
$enabled_actions = get_option('woosms_enabled_actions', array());
foreach ($enabled_actions as $action) {
    add_action($action, 'woosms_listen_for_woocommerce_actions', 10, 3);
}
