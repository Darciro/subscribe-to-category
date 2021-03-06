<?php
/**
 * 
 * Class for the settings page
 * @author Daniel Söderström <info@dcweb.nu>
 * 
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) )
  die();
  
if( class_exists( 'STC_Settings' ) ) {
  $stc_setting = new STC_Settings();
}

  class STC_Settings {
    
    private $options; // holds the values to be used in the fields callbacks
    private $export_in_categories = array(); // holds value for filter export categories

    /**
     * Constructor
     *
     * @since  1.0.0
     */
    public function __construct() {

      // only in admin mode
      if( is_admin() ) {    
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Ajax call for sendings emails manually
        add_action( 'wp_ajax_force_run', array( $this, 'force_run' ) );
        add_action( 'wp_ajax_remove_post_from_sending', array( $this, 'remove_post_from_sending' ) );

      }


    }

    /**
     * Ajax call for trigger send action manually
     * 
     * @since  1.1.0
     * 
     * @return [type] [description]
     */
    public function force_run(){
      check_ajax_referer( 'ajax_nonce', 'nonce' );
  
      $subscriber = STC_Subscribe::get_instance();
      $subscriber->stc_send_email();

      _e( 'Scheduled event successfully executed', 'stc_textdomain' );

      die();
    }

    /**
     * Ajax call for trigger the function to remove post from sending manually
     * 
     * @since  1.9.0
     * 
     */
    public function remove_post_from_sending(){
      check_ajax_referer( 'ajax_nonce', 'nonce' );
      
      if( update_post_meta( $_POST['post_id'], '_stc_notifier_status', 'blocked' ) ){
        _e( 'Post removed from queue of sending', 'stc_textdomain' );
      }

      die();
    }

    /**
     * Add options page
     * 
     * @since  1.0.0
     * 
     */
    public function add_plugin_page() {
      
      if( isset( $_POST['action'] ) && $_POST['action'] == 'export' ){
          
        // listen for filter by categories
        if( isset( $_POST['in_categories'] ) && !empty( $_POST['in_categories'] ) ){
          $this->export_in_categories = $_POST['in_categories']; 
        }
        $this->export_to_excel();
      
      }
      
      add_options_page(
        __( 'Subscribe to Category', 'stc_textdomain' ), 
        __( 'Subscribe', 'stc_textdomain' ), 
        'manage_options', 
        'stc-subscribe-settings', 
        array( $this, 'create_admin_page' )
      );

    }

    /**
     * Options page callback
     *
     * @since  1.0.0
     * 
     */
    public function create_admin_page() {

      // Set class property
      $this->options = get_option( 'stc_settings' );
      $time_in_seconds_i18n = strtotime( date_i18n( 'Y-m-d H:i:s' ) ) + self::get_next_cron_time( 'stc_schedule_email' );
      $next_run = gmdate( get_option('date_format') .' '. get_option('time_format'), $time_in_seconds_i18n ); 
      
      ?>
      <div class="wrap">
        <?php screen_icon(); ?>
        <h2><?php _e('Settings for subscribe to category', 'stc_textdomain' ); ?></h2>       

        <?php if( $this->options['developer_mode'] ): ?>
          <div id="stc-error-settings" class="error settings-error notice is-dismissible"> 
            <p><strong><?php _e( 'Attention, developer mode is active, no email will be sent.', 'stc_textdomain' ); ?></strong></p>
            <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dispensar este aviso.</span></button>
          </div>
        <?php endif; ?>

        <?php $posts_to_send = $this->list_posts_in_queue(); ?>
        <table class="stc-info widefat">
          <tbody>
            <?php if( empty( $posts_to_send ) ): ?>
            <tr>
              <td class="desc">
                <?php printf( __('There are no posts in queue to be sent', 'stc_textdomain' ), $next_run, '<span id="stc-posts-in-que">' . $this->get_posts_in_que() . '</span>' ); ?><br>
              </td>
            </tr>
            <?php else: ?>
            <tr>
              <td class="desc">
                <?php printf( __('Next run is going to be: <strong>%s</strong> and will include <strong>%s posts</strong>.', 'stc_textdomain' ), $next_run, '<span id="stc-posts-in-que">' . $this->get_posts_in_que() . '</span>' ); ?><br>
                <a id="link-posts-to-send" href="#">Click here to view the complete list</a>
              </td>
              <td class="textright">
                <div class="stc-force-run-action">
                  <button type="button" id="stc-force-run" class="button button-primary"><?php _e( 'Click here to run this action right now', 'stc_textdomain' ); ?></button>
                </div>
              </td>
            </tr>
            <tr>
              <td class="desc" colspan="2">
                <div class="posts-to-send">
                  <ul>
                    <?php foreach ($posts_to_send as $post) : ?>
                      <li>
                        <?php echo $post->post_title; ?>
                        <div class="stc-row-actions">
                          <span class="edit"><a href="<?php echo admin_url(); ?>post.php?post=<?php echo $post->ID; ?>&amp;action=edit">Editar</a> | </span>
                          <span class="trash"><a href="#" class="stc-remove-from-sending" data-post-id="<?php echo $post->ID; ?>">Remover da lista de envio</a> | </span>
                          <span class="view"><a href="<?php echo get_permalink($post->ID); ?>">Ver</a></span>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>

        <form method="post" action="options.php">
        <?php
            // print out all hidden setting fields
            settings_fields( 'stc_option_group' );   
            do_settings_sections( 'stc-subscribe-settings' );
            do_settings_sections( 'stc-resend-settings' );
            do_settings_sections( 'stc-style-settings' );
            do_settings_sections( 'stc-deactivation-settings' );
            do_settings_sections( 'stc-developer-settings' );
            submit_button(); 
        ?>
        </form>
        <?php $this->export_to_excel_form(); ?>

      </div>
      <?php
    }

    /**
     * Get current posts in que to be sent
     *
     * @since  1.0.0
     * 
     * @return int sum of posts
     */
    private function get_posts_in_que(){
      
      // get posts with a post meta value in outbox
      $meta_key = '_stc_notifier_status';
      $meta_value = 'outbox';

      $args = array(
        'post_type'   => 'post',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
              'key'     => $meta_key,
              'value'   => $meta_value,
              'compare' => '=',
            ),
            array(
              'key'     => $meta_key,
              'value'   => 'blocked',
              'compare' => '!=',
            ),
          )
      );

      $posts = get_posts( $args );

      return count( $posts );
    }

    /**
     * Returns the time in seconds until a specified cron job is scheduled.
     *
     * @since  1.0.0
    */
    public static function get_next_cron_time( $cron_name ){

      foreach( _get_cron_array() as $timestamp => $crons ){

        if( in_array( $cron_name, array_keys( $crons ) ) ){
          return $timestamp - time();
        }
      }

      return false;
    }

    /**
     * Register and add settings
     *
     * @since  1.0.0
     * 
     */
    public function register_settings(){        

        // General settings
        add_settings_section(
            'general_setting_id', // ID
            __( 'General settings', 'stc_textdomain' ), // Title
            '', //array( $this, 'print_section_info' ), // Callback
            'stc-subscribe-settings' // Page
        );  

        // Email settings
        add_settings_section(
            'setting_email_id', // ID
            __( 'E-mail settings', 'stc_textdomain' ), // Title
            '', //array( $this, 'print_section_info' ), // Callback
            'stc-subscribe-settings' // Page
        ); 

        add_settings_field(
            'stc_email_from',
            __( 'Recurrence of sending: ', 'stc_textdomain' ),
            array( $this, 'stc_cron_time_callback' ), // Callback
            'stc-subscribe-settings', // Page
            'general_setting_id' // Section           
        );

        add_settings_field(
            'stc_email_from',
            __( 'E-mail from: ', 'stc_textdomain' ),
            array( $this, 'stc_email_from_callback' ), // Callback
            'stc-subscribe-settings', // Page
            'setting_email_id' // Section           
        );

        add_settings_field(
            'stc_title',
            __( 'Email subject: ', 'stc_textdomain' ),
            array( $this, 'stc_title_callback' ), // Callback
            'stc-subscribe-settings', // Page
            'setting_email_id' // Section           
        );

        add_settings_field(
            'stc_email_content_length',
            __( 'Quantidade de caracteres: ', 'stc_textdomain' ),
            array( $this, 'stc_email_content_length_callback' ), // Callback
            'stc-subscribe-settings', // Page
            'setting_email_id' // Section           
        );

        /**
         * @TODO: Definir uma estrutura de personalizacao para os emails disparados
         */
        add_settings_field(
            'stc_email_template',
            __( 'Email template: ', 'stc_textdomain' ),
            // array( $this, 'stc_email_template_callback' ), // Callback
            'stc-subscribe-settings', // Page
            'setting_email_id' // Section           
        );


        // Resend settings
        add_settings_section(
            'setting_resend_id', // ID
            __( 'Resend post on update', 'stc_textdomain' ), // Title
            '', //array( $this, 'print_section_info' ), // Callback
            'stc-resend-settings' // Page
        );  

        add_settings_field(
            'stc_resend',
            __( 'Resend:', 'stc_textdomain' ),
            array( $this, 'stc_resend_callback' ), // Callback
            'stc-resend-settings', // Page
            'setting_resend_id' // Section           
        );     

        add_settings_field(
            'stc_exclude_from_send',
            __( 'Exclude from send:', 'stc_textdomain' ),
            array( $this, 'stc_exclude_from_send_callback' ), // Callback
            'stc-resend-settings', // Page
            'setting_resend_id' // Section           
        );        


        // Styleing settings
        add_settings_section(
            'setting_style_id', // ID
            __( 'Stylesheet (CSS) settings', 'stc_textdomain' ), // Title
            '', //array( $this, 'print_section_info' ), // Callback
            'stc-style-settings' // Page
        );  

        add_settings_field(
            'stc_custom_css',
            __( 'Custom CSS: ', 'stc_textdomain' ),
            array( $this, 'stc_css_callback' ), // Callback
            'stc-style-settings', // Page
            'setting_style_id' // Section           
        );

        // Developer settings
        add_settings_section(
            'setting_developer_id', // ID
            __( 'Developer settings', 'stc_textdomain' ), // Title
            '', // array( $this, 'develper_section_info' ), // Callback
            'stc-developer-settings' // Page
        );  

        add_settings_field(
            'stc_developer_mode',
            __( 'Enable developer mode: ', 'stc_textdomain' ),
            array( $this, 'stc_developer_mode_callback' ), // Callback
            'stc-developer-settings', // Page
            'setting_developer_id' // Section           
        );  


        // Deactivation settings
        add_settings_section(
            'setting_deactivation_id', // ID
            __( 'On plugin deactivation', 'stc_textdomain' ), // Title
            array( $this, 'section_deactivation_info' ), // Callback
            'stc-deactivation-settings' // Page
        );          

        add_settings_field(
            'stc_remove_subscribers',
            __( 'Subscribers: ', 'stc_textdomain' ),
            array( $this, 'stc_remove_subscribers_callback' ), // Callback
            'stc-deactivation-settings', // Page
            'setting_deactivation_id' // Section           
        );    

        register_setting(
          'stc_option_group', // Option group
          'stc_settings', // Option name
          array( $this, 'input_validate_sanitize' ) // Callback function for validate and sanitize input values
        );

    }

    /**
     * Print outs text for deactivation info
     *
     * @since  1.0.0
     * 
     * @return [type] [description]
     */
    public function section_deactivation_info(){
      ?>
      <p><?php _e('The plugin will remove all data in database created by this plugin but there is an option regarding subscribers', 'stc_textdomain' ); ?></p>
      <?php
    }

    /**
     * Sanitize setting fields
     *
     * @since  1.0.0
     * 
     * @param array $input 
     */
    public function input_validate_sanitize( $input ) {
        $output = array();

        if( isset( $input['cron_recurrence'] ) ){
          $output['cron_recurrence'] = $input['cron_recurrence'];
          $this->update_cron_recurrence( $input['cron_recurrence'] );
        }

        if( isset( $input['email_from'] ) ){

          // sanitize email input
          $output['email_from'] = sanitize_email( $input['email_from'] ); 
          
          if(! empty( $input['email_from'] )){
            if ( ! is_email( $output['email_from'] ) ){
              add_settings_error( 'setting_email_id', 'invalid-email', __( 'You have entered an invalid email.', 'stc_textdomain' ) );
            }
          }
        }

        if( isset( $input['email_content_length'] ) ){
          $output['email_content_length'] = $input['email_content_length'];
        }

        if( isset( $input['email_template'] ) ){
          $output['email_template'] = $input['email_template'];
        }

        if( isset( $input['title'] ) ){
          $output['title'] = $input['title'];
        }

        if( isset( $input['resend_option'] ) ){
          $output['resend_option'] = $input['resend_option'];
        }

        if( isset( $input['exclude_from_send_option'] ) ){
          $output['exclude_from_send_option'] = $input['exclude_from_send_option'];
        }

        if( isset( $input['exclude_css'] ) ){
          $output['exclude_css'] = $input['exclude_css'];
        }

        if( isset( $input['deactivation_remove_subscribers'] ) ){
          $output['deactivation_remove_subscribers'] = $input['deactivation_remove_subscribers'];
        }

        if( isset( $input['developer_mode'] ) ){
          $output['developer_mode'] = $input['developer_mode'];
        }

        return $output;
    }

    /** 
     * Printing section text
     *
     * @since  1.0.0
     * 
     */
    public function print_section_info(){
      _e( 'Add your E-mail settings', 'stc_textdomain' );
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_cron_time_callback() {
      $cron_recurrence = get_option( 'cron_recurrence' );
      ?>
        <label id="cron_recurrence_hourly">
          <input type="radio" id="cron_recurrence_hourly" class="regular-text" name="stc_settings[cron_recurrence]" value="hourly" <?php checked( $this->options['cron_recurrence'], 'hourly' ); ?>>
          <?php _e( 'Hourly', 'stc_textdomain' ); ?>
        </label><br>
        <label id="cron_recurrence_twicedaily">
          <input type="radio" id="cron_recurrence_twicedaily" class="regular-text" name="stc_settings[cron_recurrence]" value="twicedaily" <?php checked( $this->options['cron_recurrence'], 'twicedaily' ); ?>>
          <?php _e( 'Twicedaily', 'stc_textdomain' ); ?>
        </label><br>
        <label id="cron_recurrence_daily">
          <input type="radio" id="cron_recurrence_daily" class="regular-text" name="stc_settings[cron_recurrence]" value="daily" <?php checked( $this->options['cron_recurrence'], 'daily' ); ?>>
          <?php _e( 'Daily', 'stc_textdomain' ); ?>
        </label><br>
        <p class="description"><?php _e( 'Set recurrence for sending emails.', 'stc_textdomain' ); ?></p>
        <?php
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_email_from_callback() {
      $default_email = get_option( 'admin_email' );
      ?>
        <input type="text" id="email_from" class="regular-text" name="stc_settings[email_from]" value="<?php echo isset( $this->options['email_from'] ) ? esc_attr( $this->options['email_from'] ) : '' ?>" />
        <p class="description"><?php printf( __( 'Enter the e-mail address for the sender, if empty the admin e-mail address %s is going to be used as sender.', 'stc_textdomain' ), $default_email ); ?></p>
        <?php
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_title_callback() {
      ?>
        <input type="text" id="title" class="regular-text" name="stc_settings[title]" value="<?php echo isset( $this->options['title'] ) ? esc_attr( $this->options['title'] ) : '' ?>" />
        <p class="description"><?php _e( 'Enter e-mail subject for the e-mail notification, leave empty if you wish to use post title as email subject.', 'stc_textdomain' ); ?></p>
        <?php
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_email_content_length_callback() {
      ?>
        <input type="number" id="email_content_length" class="regular-text" name="stc_settings[email_content_length]" value="<?php echo isset( $this->options['email_content_length'] ) ? esc_attr( $this->options['email_content_length'] ) : '' ?>" />
        <p class="description"><?php _e( 'Enter the max length for the content body when sending emails', 'stc_textdomain' ); ?></p>
        <?php
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_email_template_callback() {
      ?>
        <textarea id="email_template" name="stc_settings[email_template]" cols="80" rows="10"><?php echo isset( $this->options['email_template'] ) ? esc_attr( $this->options['email_template'] ) : '' ?></textarea>
        <p class="description"><?php _e( 'Enter e-mail subject for the e-mail notification, leave empty if you wish to use post title as email subject.', 'stc_textdomain' ); ?></p>
        <?php
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.2.0
     * 
     */
    public function stc_resend_callback() { 
      $options['resend_option'] = '';
      
      if( isset( $this->options['resend_option'] ) )
        $options['resend_option'] = $this->options['resend_option'];
      ?>

      <label for="resend_option"><input type="checkbox" value="1" id="resend_option" name="stc_settings[resend_option]" <?php checked( '1', $options['resend_option'] ); ?>><?php _e('Enable resend post option', 'stc_textdomain' ); ?></label>
      <p class="description"><?php _e('Gives an option on edit post (in the publish panel) to resend a post on update.', 'stc_textdomain' ); ?></p>
    <?php
    }    

    /** 
     * Add option to remove post from send
     *
     * @since  1.9.0
     * 
     */
    public function stc_exclude_from_send_callback() { 
      $options['exclude_from_send_option'] = '';
      
      if( isset( $this->options['exclude_from_send_option'] ) )
        $options['exclude_from_send_option'] = $this->options['exclude_from_send_option'];
      ?>

      <label for="exclude_from_send_option"><input type="checkbox" value="1" id="exclude_from_send_option" name="stc_settings[exclude_from_send_option]" <?php checked( '1', $options['exclude_from_send_option'] ); ?>><?php _e('Enable exclude from send post option', 'stc_textdomain' ); ?></label>
      <p class="description"><?php _e('Gives an option on edit post (in the publish panel) to exclude post from send.', 'stc_textdomain' ); ?></p>
    <?php
    }   

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_css_callback() { 
      $options['exclude_css'] = '';
      
      if( isset( $this->options['exclude_css'] ) )
        $options['exclude_css'] = $this->options['exclude_css'];
      ?>

      <label for="exclude_css"><input type="checkbox" value="1" id="exclude_css" name="stc_settings[exclude_css]" <?php checked( '1', $options['exclude_css'] ); ?>><?php _e('Exclude custom CSS', 'stc_textdomain' ); ?></label>
      <p class="description"><?php _e('Check this option if your theme supports Bootstrap framework or if you want to place your own CSS for Subscribe to Category in your theme.', 'stc_textdomain' ); ?></p>
    <?php
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_remove_subscribers_callback() { 
      $options['deactivation_remove_subscribers'] = '';
      
      if( isset( $this->options['deactivation_remove_subscribers'] ) )
        $options['deactivation_remove_subscribers'] = $this->options['deactivation_remove_subscribers'];
  
      ?>

      <label for="deactivation_remove_subscribers"><input type="checkbox" value="1" id="deactivation_remove_subscribers" name="stc_settings[deactivation_remove_subscribers]" <?php checked( '1', $options['deactivation_remove_subscribers'] ); ?>><?php _e('Delete all subscribers on deactivation', 'stc_textdomain' ); ?></label>
    <?php
    }  

    /** 
     * Get the settings option array and print one of its values
     *
     * @since  1.0.0
     * 
     */
    public function stc_developer_mode_callback() { 
      $options['developer_mode'] = '';
      
      if( isset( $this->options['developer_mode'] ) )
        $options['developer_mode'] = $this->options['developer_mode'];
  
      ?>

      <label for="developer_mode"><input type="checkbox" value="1" id="developer_mode" name="stc_settings[developer_mode]" <?php checked( '1', $options['developer_mode'] ); ?>><?php _e('Yes', 'stc_textdomain' ); ?></label>
    <?php
    }        

    /**
     * Form for filtering categories on export to excel
     *
     * @since  1.0.0
     * 
     */
    public function export_to_excel_form(){
      $categories = get_categories( array( 'hide_empty' => false ) ); 
      ?>
      <h3><?php _e( 'Export to excel', 'stc_textdomain' ); ?></h3>
      <form method="post" action="options-general.php?page=stc-subscribe-settings">
      <table class="form-table export-table">
        <tbody>
          <tr>
            <th scope="row">
              <?php _e('Filter by categories', 'stc_textdomain' ); ?><br>
              <a href="#" class="select-all-categories-to-export"><small>Select/Deselect all</small></a>
            </th>
            <td>
              <?php if(! empty( $categories )) : ?>
                <?php foreach( $categories as $cat ) : ?>
                  <label for="<?php echo $cat->slug; ?>"><input type="checkbox" name="in_categories[]" id="<?php echo $cat->slug; ?>" value="<?php echo $cat->term_id; ?>"><?php echo $cat->name; ?></label>
                <?php endforeach; ?>
              <?php else: ?>
                <?php _e('There are no categories to list yet', 'stc_textdomain' ); ?>
              <?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>
      <input type="hidden" value="export" name="action">
      <input type="submit" value="Export to Excel" class="button button-primary" id="submit" name="">
      </form>
      
      <?php
    }

    /**
     * Export method for excel
     *
     * @since  1.0.0
     * 
     */
    public function export_to_excel(){

      $args = array(
        'post_type'     => 'stc',
        'post_status'   => 'publish',
        'category__in'  => $this->export_in_categories // Empty value returns all categories
      );

      $posts = get_posts( $args );

      // get category names for filtered categories to print out in excel file, if there is a filter...
      if(!empty( $this->export_in_categories)){
        
        foreach ( $this->export_in_categories as $item ) {
          $cats = get_term( $item, 'category' );
          $cats_name .= $cats->name.', ';
        }
        // remove last commasign in str
        $in_category_name = substr( $cats_name, 0, -2 );
      }

      $i = 0;
      $export = array();
      foreach ($posts as $p) {
        
        $cats = get_the_category( $p->ID ); 
        foreach ($cats as $c) {
          $c_name .= $c->name . ', ';
        }
        $in_categories = substr( $c_name, 0, -2);
        $c_name = false; // unset variable

        $export[$i]['id'] = $p->ID;
        $export[$i]['email'] = $p->post_title;
        $export[$i]['user_categories'] = $in_categories;
        $export[$i]['subscription_date'] = $p->post_date;
        
        $i++;
      }
      
      // filename for download 
      $time = date('Ymd_His'); 
      $filename = STC_SLUG . '_' . $time . '.xls';

      header("Content-Disposition: attachment; filename=\"$filename\""); 
      header("Content-Type:   application/vnd.ms-excel; ");
      header("Content-type:   application/x-msexcel; ");

      $flag = false; 
      // print out filtered categories if there is
      if(!empty( $in_category_name ))
        echo "\r\n", utf8_decode( __('Filtered by: ', 'stc_textdomain' ) ) . utf8_decode( $in_category_name ); 
      
      foreach ($export as $row ) {
        if(! $flag ) { 
          // display field/column names as first row 
          echo "\r\n" . implode("\t", array_keys( $row )) . "\r\n"; 
          $flag = true; 
        } 

        array_walk($row, array($this, 'clean_data_for_excel') ); 
        echo implode("\t", array_values($row) ). "\r\n"; 
      } 

      exit;

    }
      
    /**
     * Method for cleaning data to excel
     *
     * @since  1.0.0
     * 
     */
    public function clean_data_for_excel( &$str ) { 
      $str = iconv('UTF-8', 'ISO-8859-1', $str );
      $str = preg_replace("/\t/", "\\t", $str ); 
      $str = preg_replace("/\r?\n/", "\\n", $str ); 
    } 

    /**
     * Update database for cron interval
     *
     * @since  1.9.0
     * 
     * @param string $interval 
     */
    public function update_cron_recurrence( $interval ) {
      global $wpdb;
      $options_table = $wpdb->prefix . 'options';
      $cron_jobs = get_option( 'cron' );
      if( $interval == 'daily' ){
        $timestamp_for_cron = 86400;
      } elseif( $interval == 'twicedaily' ){
        $timestamp_for_cron = 43200;
      } else { // hourly
        $timestamp_for_cron = 3600;
      }

      $stc_schedule_email_key = '';
      foreach ($cron_jobs as $key => $arr) {
        if( key($arr) === 'stc_schedule_email' ){
          $cron_jobs[$key]['stc_schedule_email'][key(array_values($arr)[0])]['schedule'] = $interval;
          $cron_jobs[$key]['stc_schedule_email'][key(array_values($arr)[0])]['interval'] = $timestamp_for_cron;
          $stc_schedule_email_key = $key;
        }
      }

      $cron_jobs[ time() + $timestamp_for_cron ] = $cron_jobs[$stc_schedule_email_key];
      unset($cron_jobs[$stc_schedule_email_key]);

      $update = $wpdb->update(
        $options_table,
        array( 
            'option_value' => serialize($cron_jobs)
        ),  
        array( 'option_name' => 'cron' )
      );

      if( !$update ){
        error_log($wpdb->last_error, 0);
      }
    }

    /**
     * Get current posts in que to be sent
     *
     * @since  1.0.0
     * 
     * @return int sum of posts
     */
    private function list_posts_in_queue(){
      
      // get posts with a post meta value in outbox
      $meta_key = '_stc_notifier_status';
      $meta_value = 'outbox';

      $args = array(
        'post_type'   => 'post',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
              'key'     => $meta_key,
              'value'   => $meta_value,
              'compare' => '=',
            ),
            array(
              'key'     => $meta_key,
              'value'   => 'blocked',
              'compare' => '!=',
            ),
          )
      );

      $posts = get_posts( $args );

      return $posts;
    }

  }

?>