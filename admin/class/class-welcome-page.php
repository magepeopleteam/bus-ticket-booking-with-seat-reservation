<?php
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 * @package WBTM_Plugin
 */

class WBTM_Welcome
{
  public function __construct(){
    add_action("admin_menu",array($this,"WBTM_welcome_init"));
  }
  
  public function WBTM_welcome_init(){
    add_submenu_page(
        'edit.php?post_type=wbtm_bus',
        __( 'Welcome to WBTM', 'bus-ticket-booking-with-seat-reservation' ),
        '<span style="color:#13df13">'.__( 'Welcome', 'bus-ticket-booking-with-seat-reservation' ).'</span>',
        'manage_options',
        'wbtm_welcome',
        array($this,"WBTM_welcome_page_callback")
    );
  } 

  public function WBTM_welcome_page_callback(){
    $pro_badge = '<span class="badge">'.__( "PRO", "bus-ticket-booking-with-seat-reservation" ).'</span>';
    $arr = array( 'strong' => array() );
    ?>
    <div class="wrap wbtm_welcome_wrap">
    <?php settings_errors(); ?>
        <h1><?php _e( 'Welcome to Bus Ticket Booking with Seat Reservation', 'bus-ticket-booking-with-seat-reservation' ); ?></h1>
            <ul class="tabs">
                <li class="tab-link current" data-tab="tab-1"><?php _e( 'Import', 'bus-ticket-booking-with-seat-reservation' ); ?></li>
                <li class="tab-link" data-tab="tab-2"><?php _e( 'Knowledge Base', 'bus-ticket-booking-with-seat-reservation' ); ?></li>
            </ul>
            <!-- Start Tab One Content -->
            <div id="tab-1" class="tab-content current">
            <h1><?php _e( 'How to Import Dummy Content', 'bus-ticket-booking-with-seat-reservation' ); ?></h1>
            <p><?php _e( 'Please follow the below process to import dummy event data to your website.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
            <table class="wbtm_import_process">
                <tr>
                    <td><strong><?php _e( 'Step-1:', 'bus-ticket-booking-with-seat-reservation' ); ?></strong></td>
                    <td>
                        <p><?php echo wp_kses( 'Please click on the XML files link and press <strong>Ctrl+S</strong> in Windows or <strong>Cmd+S</strong> on a Mac to save the file to your computer directory.', $arr ); ?></p>
                        <ol>
                            <li><a href="https://bus.mage-people.com/category.xml" target="_blank"><?php _e( 'category.xml', 'bus-ticket-booking-with-seat-reservation' ); ?></a></li>
                            <li><a href="https://bus.mage-people.com/content.xml" target="_blank"><?php _e( 'content.xml', 'bus-ticket-booking-with-seat-reservation' ); ?></a></li>
                        </ol>
                        <p><?php _e( 'Or, you may download the XML files zip and extract it to your computer directory.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <a href="https://bus.mage-people.com/bus-dummy-content.zip" target="_blank"><?php _e( 'Download the Zip of XML Files', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php _e( 'Step-2:', 'bus-ticket-booking-with-seat-reservation' ); ?></strong></td>
                    <td>
                        <p><?php _e( 'After Download the XML file, Go to:', 'bus-ticket-booking-with-seat-reservation' ); ?> <strong><?php _e( 'Tools -> Import', 'bus-ticket-booking-with-seat-reservation' ); ?></strong></p>
                        <p><?php _e( 'In the bottom of this page there is a WordPress import option. If you have already enabled this, you can see Run Import. If not, click on the Install Now link. After that click on Run Importer and select the XML file you downlaoded earlier.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php _e( 'Step-3:', 'bus-ticket-booking-with-seat-reservation' ); ?></strong></td>
                    <td>
                        <p><?php _e( 'Now select the user of your website to assign the contents from the dropdown, and tick on the Download Attachemnt tick box & Run the Process. After a few minute you will see the Success message. All Done! Have Fun.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                    </td>
                </tr>                
            </table>
            <h1><?php _e( 'Video Tutorial', 'bus-ticket-booking-with-seat-reservation' ); ?></h1>
            <iframe width="799" height="427" src="<?php echo esc_url('https://www.youtube.com/embed/ejUILQ8dCvQ'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
             <!-- End Start Tab One Content -->

             <!-- Start Tab Two Content --> 
            <div id="tab-2" class="tab-content">
            <h1><?php _e( 'Welcome to Documentation of Bus Ticket Booking with Seat Reservation Plugin', 'bus-ticket-booking-with-seat-reservation' ); ?></h1>
            <a href="<?php echo esc_url('https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/'); ?>" class="wbtm-top-pro-btn"><?php _e( 'BUY PRO', 'bus-ticket-booking-with-seat-reservation' ); ?></a>    
            <ul class="accordion">
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to install the plugin?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch the video to know how you can install the plugin.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/1kY9vFIJdE4'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>  
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to add a New Bus?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'After install the plugin you can add a new bus. Please watch the video to know how you can do it:', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/N_6MbfzZw84'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to book a bus ticket?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'In this video you can know how easy to place a order to book a bus ticket:', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/vAMln7298eg'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to setup PDF ticket?', 'bus-ticket-booking-with-seat-reservation' ); ?><?php echo $pro_badge; //escaped already above ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch the video to know how to setup PDF ticket.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/8F_Jw2_alGw'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to setup email?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch the video to know how to setup Email.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/hbc0kYd8zA8'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to set bus on ticket price?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch this video to know how to set bus on ticket price.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/5XNiRwl9VAM'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to set bus on specific day?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch this video to know how to set bus on specific day.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/z18HXrPf0-Q'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to set bus booking buffer time?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch this video to know how to set bus booking on buffer time.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/7McbXsaPHEg'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to get ticket from admin panel?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch this video to know how to get ticket from admin panel.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/TmB_FEbQagk'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to export passenger list in CSV?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch this video to know how to export passenger list in CSV.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/9ODsKeFwMpY'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to create two door and four column seat plan?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch this video to know how to create two door and four column seat plan.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/Mh_2UUKo8Nk'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to create three column seat plan?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch this video to know how to create three column seat plan.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/2yEfMio10-I'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to booking bus?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Please watch this video to know how to booking bus.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <iframe width="500" height="281" src="<?php echo esc_url('https://www.youtube.com/embed/fK1-JCuI9rY'); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </li>
                <li>
                    <a class="toggle" href="javascript:void(0);"><?php _e( 'How to buy PRO version?', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    <div class="inner">
                        <p><?php _e( 'Bus Ticket Booking with Seat Reservation PRO', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                        <a href="<?php echo esc_url('https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/'); ?>" class="wbtm-d-btn"><?php _e( 'BUY PRO', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
                    </div>
                </li>                                                                                                                                                                                           
                </ul>
                <h1 class="mt-10"><?php _e( 'Shortcodes included with Bus Ticket Booking with Seat Reservation Plugin:', 'bus-ticket-booking-with-seat-reservation' ); ?></h1>
                <table>
                    <tr>
                        <th><?php _e( 'Shortcode', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th><?php _e( 'Parameter', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th><?php _e( 'Description', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                    </tr>
                    <tr>
                        <td rowspan="2"><code>[wbtm-bus-list cat=''show='']</code></td>
                        <td><code><?php _e( 'cat', 'bus-ticket-booking-with-seat-reservation' ); ?></code></td>
                        <td><?php _e( 'By default showing all bus, but if you want to show bus list of a particular category you can use this attribute, just put the category id with this. Example:', 'bus-ticket-booking-with-seat-reservation' ); ?> <code>[wbtm-bus-list cat='ID']</code></td>
                    </tr>
                    <tr>           
                        <td><code><?php _e( 'show', 'bus-ticket-booking-with-seat-reservation' ); ?></code></td>
                        <td><?php _e( 'By default showing 20 bus per page. If you want to change it and set limit input the limit number. example:', 'bus-ticket-booking-with-seat-reservation' ); ?> <code>[wbtm-bus-list show='10']</code></td>
                    </tr>
                    <tr>
                        <td><code>[view-ticket]</code></td>
                        <td></td>
                        <td><?php _e( 'This shortcode will show the Create Ticket form. A user needs to put their ticket pin into the box and hit the enter button then the ticket will appear. Only login users can view this page.', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>[wbtm-bus-search-form]</code></td>
                        <td></td>
                        <td><?php _e( 'This shortcode will display the search form like if you want to show a search box into somewhere in the homepage or any page then just put this shortcode there it will print the bus search form.', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>[wbtm-bus-search]</code></td>
                        <td></td>
                        <td><?php _e( 'This shortcode will also print a bus search form but it will also show the search result on the same page, below of the search form.', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
                    </tr>                    
                </table>
            </div>
            <!-- End Tab Two Content --> 
    </div>
    <style>
        .wbtm_welcome_wrap h1{
            margin-bottom: 20px;           
        }
        .wbtm_welcome_wrap #tab-1 h1{
            display:block !important;
        }
        .wbtm_welcome_wrap ul.tabs{
			margin: 0px;
			padding: 0px;
			list-style: none;
		}
		.wbtm_welcome_wrap ul.tabs li{
			background: #DBDBDB;
			color: #222;
			display: inline-block;
			padding: 10px 15px;
			cursor: pointer;
		}

		.wbtm_welcome_wrap ul.tabs li.current{
			background: #fff;
			color: #222;
		}

		.wbtm_welcome_wrap .tab-content{
			display: none;
			background: #fff;
			padding: 15px;
            position: relative;
		}

		.wbtm_welcome_wrap .tab-content.current{
			display: inherit;
		}
        .wbtm_welcome_wrap .wbtm-d-btn{
            color: #fff;
            background-color: #337ab7;
            border-color: #2e6da4;
            border-radius: 3px;            
            font-family: inherit;
            font-size: .875rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-block;
            line-height: 1.125rem;
            padding: .5rem 1rem;
            margin: 0;
            height: auto;
            border: 1px solid transparent;
            vertical-align: middle;
            -webkit-appearance: none;
            text-decoration:none;
            margin-right:10px;
        }
        .wbtm_welcome_wrap .wbtm-top-pro-btn{
            color: #fff;
            background: #f95656;
            border-color: #2e6da4;
            border-radius: 3px;            
            font-family: inherit;
            font-size: .875rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-block;
            line-height: 1.125rem;
            padding: .5rem 1rem;
            margin: 0;
            height: auto;
            border: 1px solid transparent;
            vertical-align: middle;
            -webkit-appearance: none;
            text-decoration:none;      
        }
        @media only screen and (min-width: 768px) {
            .wbtm_welcome_wrap .wbtm-top-pro-btn{
                position: absolute;
                right: 20px;
                top: 20px;
            }
        }        
        .wbtm_welcome_wrap .wbtm-d-btn:hover, .wbtm_welcome_wrap .wbtm-top-pro-btn:hover{
            color:#fff;
        }
        .wbtm_welcome_wrap ul.accordion {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .wbtm_welcome_wrap ul.accordion .inner{
            overflow: hidden;
            display: none;
            background: #fbfbfb;
            border: 1px solid #d3d3d3;
            padding: 20px;   
        }
        .wbtm_welcome_wrap ul.accordion a.toggle {
            width: 100%;
            display: block;
            background: rgba(0,0,0,0.78);
            color: #fefefe;
            padding: .75em;
            border-radius: 0.15em;
            transition: .3s ease;
            max-width: -moz-available;
            max-width: -webkit-fill-available;
        }
        .wbtm_welcome_wrap ul.accordion li{
            margin: .5em 0;
        }
        .wbtm_welcome_wrap ul.accordion li:hover {
            background: rgba(0, 0, 0, 0.9);
        }
        .wbtm_welcome_wrap ul.accordion a{
            text-decoration: none;
        }
        .wbtm_welcome_wrap ul.accordion .toggle:after {
            content: "\f132";
            font-size: 16px;
            color: #fff;
            float: right;
            margin-left: 5px;
            width: 20px;
            height: 20px;
            text-align: center;
            font-family: dashicons;
            display: inline-block;
        }
        .wbtm_welcome_wrap ul.accordion .toggle.active:after {
            content: "\f460";
        }
        .wbtm_welcome_wrap ul.accordion a.toggle.active{
            background-color: #438243;
        }
        .wbtm_welcome_wrap p{
            margin-top:0;
        }
        .wbtm_welcome_wrap .badge{
            background: #f95656;
            padding: 0px 5px 0px 5px;
            border-radius: 5px;
            margin-left: 5px;
        }
        .wbtm_welcome_wrap table {
            border-collapse: collapse;
            width: 100%;
        }
        .wbtm_welcome_wrap td, .wbtm_welcome_wrap th {
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
        }
        .wbtm_welcome_wrap th{
            background-color: #dddddd;
        }
        .wbtm_welcome_wrap tr td:nth-child(1){
            width: 25%;
        }
        .wbtm_welcome_wrap code{
            background: #efff004a;
            font-weight: bold;
            border: 1px solid #b7b787;
            display: inline-block;        
        }
        .wbtm_welcome_wrap .mt-10{
            margin-top: 10px;
        }
        .wbtm_import_process tr:nth-child(odd){
            background-color: #f5f5f5;
        }
        .wbtm_import_process tr:nth-child(even){
            background-color: #f0f8ff;
        }        
        .wbtm_import_process tr td:nth-child(1) {
            width: 10%;
            text-align: center;
        }
        .wbtm_import_process{
            margin-bottom: 20px;
        }
    </style>
    <script>
    jQuery(document).ready(function(){
	
        jQuery('.wbtm_welcome_wrap ul.tabs li').click(function(){
		var tab_id = jQuery(this).attr('data-tab');

		jQuery('.wbtm_welcome_wrap ul.tabs li').removeClass('current');
		jQuery('.wbtm_welcome_wrap .tab-content').removeClass('current');

		jQuery(this).addClass('current');
		jQuery("#"+tab_id).addClass('current');
	    });

        jQuery('.wbtm_welcome_wrap ul.accordion .toggle').click(function(e) {
            e.preventDefault();
        
            var $this = jQuery(this);
        
            if ($this.next().hasClass('show')) {
                $this.next().removeClass('show');
                $this.removeClass('active');
                $this.next().slideUp(350);
            } else {
                $this.parent().parent().find('li .inner').removeClass('show');
                $this.parent().parent().find('li a').removeClass('active');
                $this.parent().parent().find('li .inner').slideUp(350);
                $this.next().toggleClass('show');
                $this.toggleClass('active');
                $this.next().slideToggle(350);
            }
        });   
    });
    </script>
    <?php
  }
}
new WBTM_Welcome();
