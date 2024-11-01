<?php
/*
Plugin Name: What's New for Ameba blog
Plugin URI: https://it-soudan.com/it/whats-new-for-ameba-blog/
Description: Show What's new list of Ameba blog
Version: 1.0.0
Author: koyacode
Author URI: https://it-soudan.com/
Text Domain: whats-new-for-ameba-blog
Domain Path: /languages
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

{Plugin Name} is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
{Plugin Name} is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with {Plugin Name}. If not, see {License URI}.
*/

/**
 * Amebawhatsnew_Ameba_Option_Default クラス
 *
 * Amebaフィードに関連するオプションのデフォルト値を保持するためのクラスです。
 * フィードの最大項目数、デフォルトの項目数、要約の最大長、デフォルトの要約長などを定義しています。
 *
 * @package    WhatsNewForAmebaBlog
 * @since      1.0.0
 * @version    1.0.0
 */
class Amebawhatsnew_Ameba_Option_Default
{
    /**
     * フィードの最大項目数
     */
    const FEED_NUM_MAX = 10;

    /**
     * フィードのデフォルト項目数
     */
    const FEED_NUM_DEFAULT = 4;

    /**
     * 要約の最大長
     */
    const EXCERPT_LEN_MAX = 1000;

    /**
     * 要約のデフォルトの長さ
     */
    const EXCERPT_LEN_DEFAULT = 200;

    /**
     * AmebaのID
     * @var string
     */
    public $id = '';

    /**
     * フィードの項目数
     * @var int
     */
    public $num;

    /**
     * 要約の長さ
     * @var int
     */
    public $excerpt_len;

    /**
     * Amebawhatsnew_Ameba_Option_Default クラスのインスタンスを作成します。
     * プロパティはデフォルトの値に設定されます。
     */
    public function __construct()
    {
        $this->id = '';
        $this->num = self::FEED_NUM_DEFAULT;
        $this->excerpt_len = self::EXCERPT_LEN_DEFAULT;
    }
}
$ameba_option_default = new Amebawhatsnew_Ameba_Option_Default();

/**
 * Amebawhatsnew_Ameba_Fetcher クラス
 *
 * Amebaフィードを取得するためのクラスです。指定されたAmeba IDを使用してフィードのURLを生成し、フィードを取得します。
 *
 * @package    WhatsNewForAmebaBlog
 * @since      1.0.0
 * @version    1.0.0
 */
class Amebawhatsnew_Ameba_Fetcher
{
    /**
     * フィードURLのテンプレート
     */
    const FEED_URL_TEMPLATE = "http://rssblog.ameba.jp/%s/rss20.xml";

    /**
     * Ameba IDを元にフィードのURLを生成します。
     *
     * @param string $id Ameba ID
     * @return string 生成されたフィードのURL
     */
    private function id_to_url( $id )
    {
        if ($id === '' ) {
            return '';
        }
        $url = sprintf(self::FEED_URL_TEMPLATE, $id);
        return $url;
    }

    /**
     * 指定されたAmeba IDとオプションを使用してフィードを取得します。
     *
     * @param string $id Ameba ID
     * @param Amebawhatsnew_Ameba_Option_Default $ameba_option_default Amebaオプションのデフォルト値を保持するインスタンス
     * @return SimplePie|WP_Error 取得したフィードのSimplePieオブジェクトまたはエラー
     */
    public function fetch( $id, $ameba_option_default )
    {
        $url = $this->id_to_url($id);
        if ($url === '' ) {
            return "url:" . $url . ", id:" . $id . ", ameba_option_default->id:" . $ameba_option_default->id;
        }

        if (function_exists('esc_url_raw') ) {
            $url = esc_url_raw($url);
        } else {
            $url = esc_url($url);
        }

        if (function_exists('fetch_feed') ) {
            $rss = fetch_feed($url);
            if (is_wp_error($rss) ) {
                return new WP_Error('fetch_feed_failed', 'Could not fetch feed');
            }
        } else {
            include_once ABSPATH . WPINC . '/feed.php';
            $rss = fetch_feed($url);
            if (is_wp_error($rss)) {
                return new WP_Error('fetch_feed_failed', 'Could not fetch feed');
            }
        }

        return $rss;
    }
}

/**
 * Amebawhatsnew_Rss_Converter クラス
 *
 * RSSフィードをHTML形式に変換するためのクラスです。フィードの取得数と要約の長さを指定して、各フィードアイテムのタイトル、要約、日付を含むHTMLリストを生成します。
 *
 * @package    WhatsNewForAmebaBlog
 * @since      1.0.0
 * @version    1.0.0
 */
class Amebawhatsnew_Rss_Converter
{

    /**
     * 指定された文字列からHTMLタグを取り除きますが、'<p>'タグは取り除かれません。
     *
     * @param  string $description HTMLタグを取り除く前の文字列
     * @return string HTMLタグが取り除かれた後の文字列。ただし、'<p>'タグは保持されます。
     *
     * @access private
     */
    private function strip_tags( $description )
    {
        return strip_tags($description, '<p>');
    }

    /**
     * 指定された文字列内の'<p>'タグの属性をすべて取り除きます。
     *
     * @param  string $description '<p>'タグの属性を取り除く前の文字列
     * @return string '<p>'タグの属性が取り除かれた後の文字列
     *
     * @access private
     */
    private function strip_p( $description )
    {
        $description = preg_replace('/<p.*?>/', '<p>', $description);
        return $description;
    }

    /**
     * 指定された文字列内の'<p>'と'</p>'のタグの数が一致するかどうかを確認します。
     *
     * @param  string $description '<p>'と'</p>'のタグの数を確認する対象の文字列
     * @return bool '<p>'と'</p>'のタグの数が一致する場合はtrue、それ以外の場合はfalse
     *
     * @access private
     */
    private function check_p( $description )
    {
        $count_open = substr_count($description, '<p>');
        $count_close = substr_count($description, '</p>');
        if ($count_open == $count_close ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 指定されたRSSフィードをHTML形式に変換します。フィードのアイテム数と要約の長さは引数で指定できます。
     * アイテム数と要約の長さはそれぞれ定義された最大値と最小値の間に制限されます。
     * 結果として、各アイテムのタイトル、要約、日付が含まれたHTMLリストが生成されます。
     *
     * @param  SimplePie $rss         変換するRSSフィードオブジェクト
     * @param  int       $num         取得するフィードアイテムの数
     * @param  int       $excerpt_len フィードの要約の長さ
     * @return string HTML形式に変換されたRSSフィードのアイテムを含むリスト
     *
     * @throws Exception エラーメッセージがWordPressのエラークラスから返された場合
     */
    function rss2html( $rss, $num, $excerpt_len )
    {
        if ($num < 0 ) {
            $num = Amebawhatsnew_Ameba_Option_Default::FEED_NUM_DEFAULT;
        }
        if ($num > Amebawhatsnew_Ameba_Option_Default::FEED_NUM_MAX ) {
            $num = Amebawhatsnew_Ameba_Option_Default::FEED_NUM_MAX;
        }
        if ($excerpt_len < 0 ) {
            $excerpt_len = Amebawhatsnew_Ameba_Option_Default::EXCERPT_LEN_DEFAULT;
        }
        if ($excerpt_len > Amebawhatsnew_Ameba_Option_Default::EXCERPT_LEN_MAX ) {
            $excerpt_len = Amebawhatsnew_Ameba_Option_Default::EXCERPT_LEN_MAX;
        }

        if (! is_wp_error($rss) ) {
            $maxitems = $rss->get_item_quantity($num);
            $rss_items = $rss->get_items(0, $maxitems);
        }
    
        $list = '<div class="ameba-whats-new"><ul class="ameba-whats-new-list">';
        if (!isset($rss_items) ) {
            // no new item
            $list = esc_html__('There is no post.', 'whats-new-for-ameba-blog');
        } else {
            foreach ( $rss_items as $i ) {
                $permalink = esc_url($i->get_permalink());

                $title = esc_html($i->get_title());

                $description = $i->get_description();
                $description = $this->strip_tags($description);
                $description = $this->strip_p($description);

                $trimmed_description = '';
                $excerpt_len_count = $excerpt_len;
                while ( $excerpt_len_count > 0 ) {
                    $p_tag_position = mb_strpos($description, '<p>');
                    if ($p_tag_position === 0 ) {
                        $p_close_tag_position = mb_strpos($description, '</p>');
                        $description_content = mb_substr($description, $p_tag_position + mb_strlen('<p>'), $p_close_tag_position - ( $p_tag_position + mb_strlen('<p>') ));

                        // HTMLエンティティを1文字としてカウントするため、html_entity_decode()を使用
                        $trimmed_description_content = mb_substr(html_entity_decode($description_content), 0, $excerpt_len_count);
                        // HTMLエンティティをhtmlentities()でエンコード
                        $trimmed_description .= '<p>' . htmlentities($trimmed_description_content) . '</p>';

                        $excerpt_len_count -= mb_strlen(html_entity_decode($trimmed_description_content));

                        $description = mb_substr($description, $p_close_tag_position + mb_strlen('</p>'));
                    } else {
                        $description_content = mb_substr($description, 0, $p_tag_position);

                        // HTMLエンティティを1文字としてカウントするため、html_entity_decode()を使用
                        $trimmed_description_content = mb_substr(html_entity_decode($description_content), 0, $excerpt_len_count);
                        // HTMLエンティティをhtmlentities()でエンコード
                        $trimmed_description_content = htmlentities($trimmed_description_content);

                        $excerpt_len_count -= $p_tag_position;

                        $trimmed_description .= $trimmed_description_content;

                        $description = mb_substr($description, $p_tag_position);
                    }
                }
                if ($this->check_p($trimmed_description) ) {
                    $trimmed_description .= '</p>';
                }

                $date = esc_html($i->get_date("Y-m-d"));

                $list .= '<li class="ameba-whats-new-item"><div class="ameba-whats-new-title"><a href="' . $permalink . '">' . $title . '</a></div><div clas="ameba-whats-new-excerpt">' . $trimmed_description . '</div><div class="ameba-whats-new-date"><p>' . $date . '</p></div></li>';
            }
        }
        $list .= '</ul></div>';
        return $list;
    }
}

/**
 * 指定されたIDを使用してRSSフィードを取得します。
 * グローバル変数$ameba_option_defaultからオプションを読み取り、Amebawhatsnew_Ameba_Fetcherオブジェクトを作成してフィードを取得します。
 *
 * @param  int|string $id フィードを取得するための識別子（通常はAmebaブログのユーザー名またはブログID）
 * @return SimplePie|WP_Error 成功時にはSimplePieオブジェクトを返し、失敗時にはWP_Errorオブジェクトを返します。
 */
function amebawhatsnew_fetch_rss( $id )
{
    global $ameba_option_default;

    $RSSFetcher = new Amebawhatsnew_Ameba_Fetcher();
    return $RSSFetcher->fetch($id, $ameba_option_default);
}

/**
 * 指定されたRSSフィードをHTMLに変換します。
 * グローバル変数$ameba_option_defaultからオプションを読み取り、Amebawhatsnew_Rss_Converterオブジェクトを作成して変換を行います。
 *
 * @param  SimplePie $rss         SimplePieオブジェクト。これは変換を行う元のRSSフィードを表します。
 * @param  int       $num         フィード内から取得するアイテム数を指定します。
 * @param  int       $excerpt_len 抽出する記事の抜粋の長さを指定します。
 * @return string 成功時にはRSSフィードをHTMLに変換した文字列を返します。
 */
function amebawhatsnew_convert_rss( $rss, $num, $excerpt_len )
{
    global $ameba_option_default;

    $RSSConverter = new Amebawhatsnew_Rss_Converter();
    return $RSSConverter->rss2html($rss, $num, $excerpt_len);
}

/**
 * ameba-whats-new ショートコードをハンドルします。ショートコードの属性からパラメータを取得し、RSSフィードを取得してHTMLに変換します。
 * ショートコードのデフォルトのパラメータはamebawhatsnew-ameba-id, amebawhatsnew-item-num, amebawhatsnew-excerpt-lengthのオプション値として格納されています。
 * それぞれ、アメーバID、フィードのアイテム数、抜粋の長さを指定します。
 *
 * @param  array $atts ショートコードの属性。この関数では'id', 'num', 'excerpt_len' をキーとする連想配列を受け取ります。
 * @return string 成功時にはRSSフィードをHTMLに変換した文字列を返します。
 */
function amebawhatsnew_handler( $atts )
{
    global $ameba_option_default;

    $a = shortcode_atts(
        array(
        'id'  => get_option('amebawhatsnew-ameba-id'),
        'num' => get_option('amebawhatsnew-item-num', Amebawhatsnew_Ameba_Option_Default::FEED_NUM_DEFAULT),
        'excerpt_len' => get_option('amebawhatsnew-excerpt-length', Amebawhatsnew_Ameba_Option_Default::EXCERPT_LEN_DEFAULT),
        ), $atts 
    );
    $rss = amebawhatsnew_fetch_rss($a['id']);
    return amebawhatsnew_convert_rss($rss, $a['num'], $a['excerpt_len']);
}
add_shortcode('ameba-whats-new', 'amebawhatsnew_handler');

/**
 * ameba-whats-new プラグイン用のスタイルシートを登録し、WordPressのフロントエンドに追加します。
 * wp_enqueue_scriptsアクションにフックされていて、その実行時にスタイルシートをキューに追加します。
 * スタイルシートのURLは、plugins_url関数を使用してプラグインディレクトリ内の相対パスから生成されます。
 */
function amebawhatsnew_register_my_styles()
{
    wp_enqueue_style(
        'amebawhatsnew-css',
        plugins_url('css/ameba-whats-new.css', __FILE__)
    );
}
add_action('wp_enqueue_scripts', 'amebawhatsnew_register_my_styles');

/**
 * 設定ページをWordPressの管理者ダッシュボードに追加します。
 * この関数はadmin_menuアクションにフックされており、WordPress管理エリアがロードされるときに実行されます。
 * この関数はadd_options_page関数を使用して、管理者メニューに新しいオプションページを追加します。
 * 
 * @since 初版
 */
function amebawhatsnew_admin_menu()
{
    add_options_page(
        __('What\'s new for Ameba blog', 'whats-new-for-ameba-blog'),
        __('What\'s new for Ameba blog', 'whats-new-for-ameba-blog'),
        'administrator',
        'amebawhatsnew_show_admin_panel',
        'amebawhatsnew_show_admin_panel'
    );
}
add_action('admin_menu', 'amebawhatsnew_admin_menu');

/**
 * 設定ページを表示します。
 * この関数は、アメーバブログの設定値を取得し、その値を元にHTMLフォームを出力します。HTMLフォームはアメーバID、アイテム数、抜粋の長さを設定するためのものです。
 *
 * @global object $ameba_option_default アメーバブログのデフォルト設定オブジェクト
 * @since  初版
 */
function amebawhatsnew_show_admin_panel()
{
    global $ameba_option_default;

    $ameba_option_default->id = get_option('amebawhatsnew-ameba-id', "");
    $ameba_option_default->num = get_option('amebawhatsnew-item-num', Amebawhatsnew_Ameba_Option_Default::FEED_NUM_DEFAULT);
    $ameba_option_default->excerpt_len = get_option('amebawhatsnew-excerpt-length', Amebawhatsnew_Ameba_Option_Default::EXCERPT_LEN_DEFAULT);
    ?>
<div class="warp">
    <h2>What's New for Ameba blog</h2>
    <form id="ameba-id-form" method="post" action="">
        <?php wp_nonce_field('my-nonce-key', 'amebawhatsnew_admin_menu'); ?>

        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row"><label for="ameba-id"><?php esc_html_e('Ameba ID', 'whats-new-for-ameba-blog'); ?>
            (<?php esc_html_e('mandatory', 'whats-new-for-ameba-blog'); ?>): </label>
            </th>
            <td>
                <input type="text" name="ameba-id" class="regular-text" required value="<?php echo esc_attr($ameba_option_default->id); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="item-num"><?php esc_html_e('Item number', 'whats-new-for-ameba-blog'); ?>: </label></th>
            <td>
                <input type="number" name="item-num" class="small-text" min="1" max="<?php echo Amebawhatsnew_Ameba_Option_Default::FEED_NUM_MAX; ?>" value="<?php echo esc_attr($ameba_option_default->num); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="excerpt-length"><?php esc_html_e('Excerpt length', 'whats-new-for-ameba-blog'); ?>: </label></th>
            <td>
                <input type="number" name="excerpt-length" class="small-text" min="0" max="<?php echo Amebawhatsnew_Ameba_Option_Default::EXCERPT_LEN_MAX; ?>" value="<?php echo esc_attr($ameba_option_default->excerpt_len); ?>">
            </td>
        </tr>
        </tbody>
        </table>

        <p><input type="submit"
        value="<?php echo esc_attr(__('Save', 'whats-new-for-ameba-blog')); ?>"
        class="button button-primary button-large">
        </p>
    </form>
</div>
    <?php
}

/**
 * 設定ページの初期化を行います。
 * この関数は、POSTリクエストを通じて送信された設定値を検証し、アメーバID、アイテム数、抜粋の長さをアップデートします。
 * データが適切にアップデートされた後、ユーザーは設定ページにリダイレクトされます。
 *
 * @global object $ameba_option_default アメーバブログのデフォルト設定オブジェクト
 * @since  初版
 */
function amebawhatsnew_admin_init()
{
    global $ameba_option_default;

    if (! isset($_POST['amebawhatsnew_admin_menu']) || ! $_POST['amebawhatsnew_admin_menu']) {
        return;
    }

    if (! check_admin_referer('my-nonce-key', 'amebawhatsnew_admin_menu') ) {
        return;
    }

    if (! current_user_can('manage_options') ) {
        return;
    }

    $safe_ameba_id = '';
    if (isset($_POST['ameba-id']) ) {
        $ameba_id = (string) filter_input(INPUT_POST, 'ameba-id');
        $safe_ameba_id = sanitize_text_field($ameba_id);
    }
    update_option('amebawhatsnew-ameba-id', $safe_ameba_id);

    $safe_item_num = Amebawhatsnew_Ameba_Option_Default::FEED_NUM_DEFAULT;
    if (isset($_POST['item-num']) ) {
        $safe_item_num = intval($_POST['item-num']);
        if (! $safe_item_num ) {
            $safe_item_num = Amebawhatsnew_Ameba_Option_Default::FEED_NUM_DEFAULT;
        }
    }
    update_option('amebawhatsnew-item-num', $safe_item_num);

    $safe_excerpt_length = Amebawhatsnew_Ameba_Option_Default::EXCERPT_LEN_DEFAULT;
    if (isset($_POST['excerpt-length']) ) {
        $safe_excerpt_length = intval($_POST['excerpt-length']); // 0 is valid value
    }
    update_option('amebawhatsnew-excerpt-length', $safe_excerpt_length);

    wp_safe_redirect(menu_page_url('amebawhatsnew_admin_menu', false));
}
add_action('admin_init', 'amebawhatsnew_admin_init');

/**
 * 設定ページで使用するスタイルシートを登録します。
 * この関数は 'admin_enqueue_scripts' フックに接続されており、特定の管理ページにのみスタイルシートを適用します。
 *
 * @param string $hook 現在のページのフックサフィックス
 * @since 初版
 */
function amebawhatsnew_register_my_admin_styles( $hook )
{
    if ('settings_page_amebawhatsnew_show_admin_panel' != $hook ) {
        return;
    }
    wp_enqueue_style(
        'amebawhatsnew-admin-css',
        plugins_url('css/ameba-whats-new-admin.css', __FILE__)
    );
}
add_action('admin_enqueue_scripts', 'amebawhatsnew_register_my_admin_styles');

/**
 * プラグインのテキストドメインをロードします。
 * これにより、プラグインの翻訳ファイルを読み込み、多言語対応を可能にします。
 * この関数は 'plugins_loaded' フックに接続されており、すべてのプラグインがロードされた後に呼び出されます。
 * 
 * @since 初版
 */
function amebawhatsnew_load_textdomain()
{
    load_plugin_textdomain('whats-new-for-ameba-blog', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'amebawhatsnew_load_textdomain');

?>
