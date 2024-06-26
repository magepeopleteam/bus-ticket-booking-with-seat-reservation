<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MAGE_Setting_API' ) ) {
		class MAGE_Setting_API {
			protected $settings_sections = array();
			protected $settings_fields = array();
			public function __construct() { }
			function set_sections( $sections ) {
				$this->settings_sections = $sections;
				return $this;
			}
			function add_section( $section ) {
				$this->settings_sections[] = $section;
				return $this;
			}
			function set_fields( $fields ) {
				$this->settings_fields = $fields;
				return $this;
			}
			function add_field( $section, $field ) {
				$defaults                            = array(
					'name'  => '',
					'label' => '',
					'desc'  => '',
					'type'  => 'text'
				);
				$arg                                 = wp_parse_args( $field, $defaults );
				$this->settings_fields[ $section ][] = $arg;
				return $this;
			}
			function admin_init() {
				//register settings sections
				foreach ( $this->settings_sections as $section ) {
					if ( false == get_option( $section['id'] ) ) {
						add_option( $section['id'] );
					}
					if ( isset( $section['desc'] ) && ! empty( $section['desc'] ) ) {
						$section['desc'] = '<div class="inside">' . $section['desc'] . '</div>';
						$callback        = function () use ( $section ) {
							echo str_replace( '"', '\"', $section['desc'] );
						};
					} else if ( isset( $section['callback'] ) ) {
						$callback = $section['callback'];
					} else {
						$callback = null;
					}
					add_settings_section( $section['id'], $section['title'], $callback, $section['id'] );
				}
				//register settings fields
				foreach ( $this->settings_fields as $section => $field ) {
					foreach ( $field as $option ) {
						$name     = $option['name'];
						$type     = isset( $option['type'] ) ? $option['type'] : 'text';
						$label    = isset( $option['label'] ) ? $option['label'] : '';
						$callback = isset( $option['callback'] ) ? $option['callback'] : array( $this, 'callback_' . $type );
						$args     = array(
							'id'                => $name,
							'class'             => isset( $option['class'] ) ? $option['class'] : $name,
							'label_for'         => "{$section}[{$name}]",
							'desc'              => isset( $option['desc'] ) ? $option['desc'] : '',
							'name'              => $label,
							'section'           => $section,
							'size'              => isset( $option['size'] ) ? $option['size'] : null,
							'options'           => isset( $option['options'] ) ? $option['options'] : '',
							'std'               => isset( $option['default'] ) ? $option['default'] : '',
							'sanitize_callback' => isset( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : '',
							'type'              => $type,
							'placeholder'       => isset( $option['placeholder'] ) ? $option['placeholder'] : '',
							'min'               => isset( $option['min'] ) ? $option['min'] : '',
							'max'               => isset( $option['max'] ) ? $option['max'] : '',
							'step'              => isset( $option['step'] ) ? $option['step'] : '',
						);
						$label    .= $this->get_field_description( $args );
						add_settings_field( "{$section}[{$name}]", $label, $callback, $section, $section, $args );
					}
				}
				// creates our settings in the options table
				foreach ( $this->settings_sections as $section ) {
					register_setting( $section['id'], $section['id'], array( $this, 'sanitize_options' ) );
				}
			}
			public function get_field_description( $args ) {
				if ( ! empty( $args['desc'] ) ) {
					$desc = sprintf( '<br/><i class="info_text"><span class="fas fa-info-circle"></span>%s</i>', $args['desc'] );
				} else {
					$desc = '';
				}
				return $desc;
			}
			function callback_text( $args ) {
				$value       = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name        = $args['section'] . '[' . $args['id'] . ']';
				$placeholder = empty( $args['placeholder'] ) ? '' : $args['placeholder'];
				?>
                <label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>" class="formControl" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"/>
                </label>
				<?php
			}
			function callback_datepicker( $args ) {
				$date_format  = MP_Global_Function::date_picker_format();
				$now          = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$date         = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$hidden_date  = $date ? date( 'Y-m-d', strtotime( $date ) ) : '';
				$visible_date = $date ? date_i18n( $date_format, strtotime( $date ) ) : '';
				$name         = $args['section'] . '[' . $args['id'] . ']';
				?>
                <label>
                    <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $hidden_date ); ?>"/>
                    <input type="text" readonly name="" class="formControl date_type" value="<?php echo esc_attr( $visible_date ); ?>" placeholder="<?php echo esc_attr( $now ); ?>"/>
                </label>
				<?php
			}
			function callback_mp_select2( $args ) {
				$value = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name  = $args['section'] . '[' . $args['id'] . ']';
				?>
                <label>
                    <select name="<?php echo esc_attr( $name ); ?>" class="formControl mp_select2">
						<?php foreach ( $args['options'] as $key => $label ) { ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php echo esc_attr( $key == $value ? 'selected' : '' ); ?>><?php echo esc_html( $label ); ?></option>
						<?php } ?>
                    </select>
                </label>
				<?php
			}
			function callback_mp_select2_role( $args ) {
				global $wp_roles;
				$value = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name  = $args['section'] . '[' . $args['id'] . '][]';
				$value = is_array( $value ) ? $value : [ $value ];
				?>
                <label>
                    <select name="<?php echo esc_attr( $name ); ?>" class="formControl mp_select2" multiple>
						<?php foreach ( $wp_roles->roles as $key => $label ) { ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php echo in_array( $key, $value ) ? 'selected' : ''; ?>><?php echo esc_html( $label['name'] ); ?></option>
						<?php } ?>
                    </select>
                </label>
				<?php
			}
			function callback_url( $args ) {
				$this->callback_text( $args );
			}
			function callback_number( $args ) {
				$value       = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name        = $args['section'] . '[' . $args['id'] . ']';
				$placeholder = empty( $args['placeholder'] ) ? '' : $args['placeholder'];
				?>
                <label>
                    <input type="number" name="<?php echo esc_attr( $name ); ?>" class="formControl" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"
						<?php echo esc_attr( empty( $args['min'] ) ? '' : ' min="' . $args['min'] . '"' ); ?>
						<?php echo esc_attr( empty( $args['max'] ) ? '' : ' max="' . $args['max'] . '"' ); ?>
						<?php echo esc_attr( empty( $args['step'] ) ? '' : ' step="' . $args['step'] . '"' ); ?>
                    />
                </label>
				<?php
			}
			function callback_checkbox( $args ) {
				$value   = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name    = $args['section'] . '[' . $args['id'] . ']';
				$checked = checked( $value, 'on', false );
				?>
                <fieldset>
                    <label>
                        <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="off"/>
                        <input type="checkbox" class="checkbox" name="<?php echo esc_attr( $name ); ?>" value="on" <?php echo esc_attr( $checked ); ?> />
						<?php echo esc_html( $args['desc'] ); ?>
                    </label>
                </fieldset>
				<?php
			}
			function callback_switch_button( $args ) {
				$value   = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name    = $args['section'] . '[' . $args['id'] . ']';
				$checked = checked( $value, 'on', false );
				?>
                <fieldset>
                    <label class="roundSwitchLabel">
                        <input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="on" <?php echo esc_attr( $checked ); ?>>
                        <span class="roundSwitch"></span>
                    </label>
                </fieldset>
				<?php
			}
			function callback_multicheck( $args ) {
				$value = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name  = $args['section'] . '[' . $args['id'] . ']';
				?>
                <fieldset>
                    <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value=""/>
					<?php foreach ( $args['options'] as $key => $label ) { ?>
						<?php
						$checked      = $value[ $key ] ?? '0';
						$checked_data = checked( $checked, $key, false );
						$name_data    = $args['section'] . '[' . $args['id'] . ']' . '[' . $key . ']';
						?>
                        <label class="_min_200">
                            <input type="checkbox" class="checkbox" name="<?php echo esc_attr( $name_data ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php echo esc_attr( $checked_data ); ?> />
							<?php echo esc_html( $label ); ?>
                        </label>
					<?php } ?>
                </fieldset>
				<?php
			}
			function callback_radio( $args ) {
				$value = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name  = $args['section'] . '[' . $args['id'] . ']';
				?>
                <fieldset>
					<?php foreach ( $args['options'] as $key => $label ) { ?>
						<?php $checked_data = checked( $value, $key, false ); ?>
                        <label>
                            <input type="radio" class="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php echo esc_attr( $checked_data ); ?> />
							<?php echo esc_html( $label ); ?>
                        </label>
					<?php } ?>
                </fieldset>
				<?php
			}
			function callback_select( $args ) {
				$value = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name  = $args['section'] . '[' . $args['id'] . ']';
				?>
                <label>
                    <select name="<?php echo esc_attr( $name ); ?>" class="formControl">
						<?php foreach ( $args['options'] as $key => $label ) { ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php echo esc_attr( $key == $value ? 'selected' : '' ); ?>><?php echo esc_html( $label ); ?></option>
						<?php } ?>
                    </select>
                </label>
				<?php
			}
			function callback_textarea( $args ) {
				$value       = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name        = $args['section'] . '[' . $args['id'] . ']';
				$placeholder = empty( $args['placeholder'] ) ? '' : $args['placeholder'];
				?>
                <label>
                    <textarea name="<?php echo esc_attr( $name ); ?>" rows="5" cols="55" class="formControl" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_html( $value ); ?></textarea>
                </label>
				<?php
			}
			function callback_html( $args ) {
				if ( ! empty( $args['desc'] ) ) {
					?>
                    <i class="info_text">
                        <span class="fas fa-info-circle"></span>
						<?php echo esc_html( $args['desc'] ); ?>
                    </i>
					<?php
				}
			}
			function callback_wysiwyg( $args ) {
				$value = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				?>
                <div>
					<?php
						$editor_settings = array(
							'teeny'         => true,
							'textarea_name' => $args['section'] . '[' . $args['id'] . ']',
							'textarea_rows' => 15
						);
						if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
							$editor_settings = array_merge( $editor_settings, $args['options'] );
						}
						wp_editor( $value, $args['section'] . '-' . $args['id'], $editor_settings );
					?>
                </div>
				<?php
			}
			function callback_file( $args ) {
				$value       = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name        = $args['section'] . '[' . $args['id'] . ']';
				$placeholder = empty( $args['placeholder'] ) ? '' : $args['placeholder'];
				$label       = $args['options']['button_label'] ?? esc_html__( 'Choose File' );
				do_action( 'mp_add_single_image', $name, $value );
			}
			function callback_password( $args ) {
				$value       = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name        = $args['section'] . '[' . $args['id'] . ']';
				$placeholder = empty( $args['placeholder'] ) ? '' : $args['placeholder'];
				?>
                <label>
                    <input type="password" name="<?php echo esc_attr( $name ); ?>" class="formControl" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"/>
                </label>
				<?php
			}
			function callback_color( $args ) {
				$value = MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] );
				$name  = $args['section'] . '[' . $args['id'] . ']';
				?>
                <label>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>" class="formControl wp-color-picker-field" value="<?php echo esc_attr( $value ); ?>" data-default-color="<?php echo esc_attr( $args['std'] ); ?>"/>
                </label>
				<?php
			}
			function callback_pages( $args ) {
				$dropdown_args = array(
					'selected' => MP_Global_Function::get_settings( $args['section'], $args['id'], $args['std'] ),
					'name'     => $args['section'] . '[' . $args['id'] . ']',
					'id'       => $args['section'] . '[' . $args['id'] . ']',
					'echo'     => 0
				);
				echo wp_dropdown_pages( $dropdown_args );
			}
			function sanitize_options( $options ) {
				if ( ! $options ) {
					return $options;
				}
				foreach ( $options as $option_slug => $option_value ) {
					$sanitize_callback = $this->get_sanitize_callback( $option_slug );
					// If callback is set, call it
					if ( $sanitize_callback ) {
						$options[ $option_slug ] = call_user_func( $sanitize_callback, $option_value );
						continue;
					}
				}
				return $options;
			}
			function get_sanitize_callback( $slug = '' ) {
				if ( empty( $slug ) ) {
					return false;
				}
				// Iterate over registered fields and see if we can find proper callback
				foreach ( $this->settings_fields as $section => $options ) {
					foreach ( $options as $option ) {
						if ( $option['name'] != $slug ) {
							continue;
						}
						// Return the callback name
						return isset( $option['sanitize_callback'] ) && is_callable( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : false;
					}
				}
				return false;
			}
			function show_navigation() {
				$count = count( $this->settings_sections );
				if ( $count > 1 ) {
					?>
                    <ul class="tabLists bgLight">
						<?php foreach ( $this->settings_sections as $tab ) { ?>
                            <li data-tabs-target="#<?php echo esc_attr( $tab['id'] ); ?>">
                                <span class="<?php echo esc_attr( array_key_exists( 'icon', $tab ) ? $tab['icon'] : '' ); ?>"></span><?php echo esc_html( $tab['title'] ); ?>
                            </li>
						<?php } ?>
                    </ul>
					<?php
				}
			}
			function show_forms() {
				?>
				<?php foreach ( $this->settings_sections as $form ) { ?>
                    <div class="tabsItem" data-tabs="#<?php echo esc_attr( $form['id'] ); ?>">
                        <form method="post" action="options.php">
							<?php
								do_action( 'wsa_form_top_' . $form['id'], $form );
								settings_fields( $form['id'] );
								do_settings_sections( $form['id'] );
								do_action( 'wsa_form_bottom_' . $form['id'], $form );
								if ( isset( $this->settings_fields[ $form['id'] ] ) ):
									?>
                                    <div class="justifyBetween _mT">
                                        <div></div>
										<?php submit_button(); ?>
                                    </div>
								<?php endif; ?>
                        </form>
                    </div>
				<?php } ?>
				<?php
				$this->script();
			}
			function script() {
				?>
                <script>
                    jQuery(document).ready(function ($) {
                        //Initiate Color Picker
                        $('.wp-color-picker-field').wpColorPicker();
                        $('.wpsa-browse').on('click', function (event) {
                            event.preventDefault();
                            var self = $(this);
                            // Create the media frame.
                            var file_frame = wp.media.frames.file_frame = wp.media({
                                title: self.data('uploader_title'),
                                button: {
                                    text: self.data('uploader_button_text'),
                                },
                                multiple: false
                            });
                            file_frame.on('select', function () {
                                attachment = file_frame.state().get('selection').first().toJSON();
                                self.prev('.wpsa-url').val(attachment.url).change();
                            });
                            // Finally, open the modal
                            file_frame.open();
                        });
                    });
                </script>
				<?php
			}
		}
	}