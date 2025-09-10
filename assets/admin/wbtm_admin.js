//==========Price settings=================//
(function ($) {
  "use strict";
  function wbtm_reload_pricing(parent) {
    let post_id = $('[name="wbtm_post_id"]').val();
    let target = parent.find(".wbtm_price_setting_area");
    let route_infos = {};
    let count = 0;
    parent
      .find(".wbtm_stop_item")
      .each(function () {
        let infos = {};
        let place = $(this).find('[name="wbtm_route_place[]"]').val();
        let time = $(this).find('[name="wbtm_route_time[]"]').val();
        let type = $(this).find('[name="wbtm_route_type[]"]').val();
        if (place && time && type) {
          infos["place"] = place;
          infos["type"] = count < 1 ? "bp" : type;
          route_infos[count] = infos;
          count++;
        }
      })
      .promise()
      .done(function () {
        if (count > 1) {
          route_infos[count - 1]["type"] = "dp";
          $.ajax({
            type: "POST",
            url: wbtm_ajax_url,
            data: {
              action: "wbtm_reload_pricing",
              post_id: post_id,
              route_infos: route_infos,
            },
            beforeSend: function () {
              wbtm_loader(target);
            },
            success: function (data) {
              target.html(data);
              //wbtm_loaderRemove(parent);
            },
            error: function (response) {
              console.log(response);
            },
          });
        } else {
          target.html("");
        }
      });
  }
  $(document).on(
    "click",
    ".wbtm_settings_pricing_routing .wbtm_stop_item .wbtm_item_remove",
    function (e) {
      if (e.result) {
        wbtm_reload_pricing($(".wbtm_settings_pricing_routing"));
      }
    }
  );
  $(document).on(
    "change",
    '.wbtm_settings_pricing_routing [name="wbtm_route_place[]"],.wbtm_settings_pricing_routing [name="wbtm_route_type[]"]',
    function () {
      wbtm_reload_pricing($(".wbtm_settings_pricing_routing"));
    }
  );
})(jQuery);
//==========Seat plan settings=================//
(function ($) {
  "use strict";
  
  // Handle seat blocking functionality
  $(document).on("change", ".wbtm_block_seat", function() {
    let $checkbox = $(this);
    let $hiddenInput = $checkbox.siblings(".wbtm_blocked_value");
    
    if ($checkbox.is(":checked")) {
      $hiddenInput.val("1");
    } else {
      $hiddenInput.val("0");
    }
  });
  
  // Handle seat rotation functionality
  $(document).on('change', 'input[name="wbtm_enable_seat_rotation"]', function() {
    let isEnabled = $(this).is(':checked');
    let $seatPlanContainer = $('.wbtm_seat_plan_settings');
    
    if (isEnabled) {
      // Show rotation controls immediately
      $seatPlanContainer.addClass('wbtm_enable_rotation');
      $seatPlanContainer.find('.wbtm_seat_rotation_controls').show();
      
      // Add rotation controls to existing seats if they don't have them
      $seatPlanContainer.find('.wbtm_seat_container').each(function() {
        let $container = $(this);
        if ($container.find('.wbtm_seat_rotation_controls').length === 0) {
          let $input = $container.find('input[class*="wbtm_id_validation"]');
          let inputName = $input.attr('name');
          let seatKey = inputName.replace('wbtm_', '').replace('[]', '');
          
          let rotationControls = `
            <div class="wbtm_seat_rotation_controls">
              <button type="button" class="wbtm_rotate_seat _whiteButton_xs" 
                      data-seat-key="${seatKey}" 
                      data-rotation="0"
                      title="Rotate Seat">
                <span class="fas fa-redo-alt mp_zero"></span>
              </button>
              <input type="hidden" name="wbtm_${seatKey}_rotation[]" 
                     value="0" 
                     class="wbtm_rotation_value" />
            </div>
          `;
          $container.append(rotationControls);
        }
      });
    } else {
      // Hide rotation controls immediately
      $seatPlanContainer.removeClass('wbtm_enable_rotation');
      $seatPlanContainer.find('.wbtm_seat_rotation_controls').hide();
    }
  });
  
  // Initialize rotation setting on page load
  $(document).ready(function() {
    let $rotationToggle = $('input[name="wbtm_enable_seat_rotation"]');
    if ($rotationToggle.is(':checked')) {
      $('.wbtm_seat_plan_settings').addClass('wbtm_enable_rotation');
      $('.wbtm_seat_rotation_controls').show();
    }
  });
  
})(jQuery);
//==========Pickup settings=================//
(function ($) {
  "use strict";
  $(document).on("click", ".wbtm_add_group_pickup", function () {
    let parent = $(this).closest(".wbtm_settings_area");
    let target_item = $(this)
      .next($(".wbtm_hidden_content"))
      .find(" .wbtm_hidden_item");
    let item = target_item.html();
    wbtm_load_sortable_datepicker(parent, item);
    let unique_id = Math.floor(Math.random() * 9999 + 9999);
    target_item.find('[name="wbtm_pickup_unique_id[]"]').val(unique_id);
    target_item
      .find('[name*="wbtm_bp_pickup"]')
      .attr("name", "wbtm_bp_pickup[" + unique_id + "]");
    target_item
      .find('[name*="wbtm_pickup_name"]')
      .attr("name", "wbtm_pickup_name[" + unique_id + "][]");
    target_item
      .find('[name*="wbtm_pickup_time"]')
      .attr("name", "wbtm_pickup_time[" + unique_id + "][]");
  });
  $(document).on("click", ".wbtm_add_group_drop_off", function () {
    let parent = $(this).closest(".wbtm_settings_area");
    let target_item = $(this)
      .next($(".wbtm_hidden_content"))
      .find(" .wbtm_hidden_item");
    let item = target_item.html();
    wbtm_load_sortable_datepicker(parent, item);
    let unique_id = Math.floor(Math.random() * 9999 + 9999);
    target_item.find('[name="wbtm_drop_off_unique_id[]"]').val(unique_id);
    target_item
      .find('[name*="wbtm_dp_pickup"]')
      .attr("name", "wbtm_dp_pickup[" + unique_id + "]");
    target_item
      .find('[name*="wbtm_drop_off_name"]')
      .attr("name", "wbtm_drop_off_name[" + unique_id + "][]");
    target_item
      .find('[name*="wbtm_drop_off_time"]')
      .attr("name", "wbtm_drop_off_time[" + unique_id + "][]");
  });
})(jQuery);

//==========Seat Rotation=================//
(function ($) {
  "use strict";
  
  // Handle seat rotation button clicks
  $(document).on("click", ".wbtm_rotate_seat", function (e) {
    e.preventDefault();
    
    let $button = $(this);
    let $rotationInput = $button.siblings('.wbtm_rotation_value');
    let currentRotation = parseInt($rotationInput.val()) || 0;
    
    // Calculate next rotation (0 -> 90 -> 180 -> 270 -> 0)
    let newRotation = (currentRotation + 90) % 360;
    
    // Update the hidden input value
    $rotationInput.val(newRotation);
    
    // Update button appearance
    $button.removeClass('rotated-90 rotated-180 rotated-270');
    if (newRotation > 0) {
      $button.addClass('rotated-' + newRotation);
    }
    
    // Update data attribute for visual feedback
    $button.attr('data-rotation', newRotation);
  });
  
  // Initialize rotation buttons when seat plan is loaded
  $(document).on('DOMNodeInserted', '.wbtm_seat_plan_preview, .wbtm_seat_plan_preview_dd', function() {
    initializeRotationButtons();
  });
  
  // Also initialize on page load
  $(document).ready(function() {
    initializeRotationButtons();
    initializeAdvancedFeaturesToggle();
  });
  
  function initializeRotationButtons() {
    $('.wbtm_rotate_seat').each(function() {
      let $button = $(this);
      let rotation = parseInt($button.attr('data-rotation')) || 0;
      
      // Apply initial rotation class
      if (rotation > 0) {
        $button.removeClass('rotated-90 rotated-180 rotated-270');
        $button.addClass('rotated-' + rotation);
      }
    });
  }
  
  // Advanced Seat Features Toggle Functionality
  function initializeAdvancedFeaturesToggle() {
    // Handle toggle switch change for advanced seat features
    $(document).on('change', 'input[name="wbtm_enable_advanced_seat_features"]', function() {
      let isEnabled = $(this).is(':checked');
      toggleAdvancedSeatFeatures(isEnabled);
    });
    
    // Initialize on page load based on current state
    let isAdvancedEnabled = $('input[name="wbtm_enable_advanced_seat_features"]').is(':checked');
    toggleAdvancedSeatFeatures(isAdvancedEnabled);
  }
  
  function toggleAdvancedSeatFeatures(isEnabled) {
    let $seatPlanContainer = $('.wbtm_seat_plan_settings');
    
    // Check if Pro addon is available (passed from PHP via wp_localize_script)
    let hasProAddon = (typeof wbtm_admin_data !== 'undefined' && wbtm_admin_data.has_pro_addon) || false;
    
    if (isEnabled && hasProAddon) {
      // Show advanced features
      $('.wbtm_seat_container').addClass('wbtm_advanced_features_enabled');
      $('.wbtm_seat_block_controls').show();
      $('.wbtm_seat_price_controls').show();
      
      // Add advanced controls to existing seats if they don't have them
      $seatPlanContainer.find('.wbtm_seat_container').each(function() {
        let $container = $(this);
        let $input = $container.find('input[class*="wbtm_id_validation"]');
        let inputName = $input.attr('name');
        
        if (inputName) {
          let seatKey = inputName.replace('wbtm_', '').replace('[]', '');
          
          // Add block controls if they don't exist
          if ($container.find('.wbtm_seat_block_controls').length === 0) {
            let blockControls = `
              <div class="wbtm_seat_block_controls">
                <label>
                  <input type="checkbox" class="wbtm_block_seat" 
                         name="wbtm_${seatKey}_blocked[]" 
                         value="1" 
                         title="Block this seat" />
                  Block
                </label>
                <input type="hidden" name="wbtm_${seatKey}_blocked_hidden[]" 
                       value="0" 
                       class="wbtm_blocked_value" />
              </div>
            `;
            $container.append(blockControls);
          }
          
          // Add price controls if they don't exist
          if ($container.find('.wbtm_seat_price_controls').length === 0) {
            let priceControls = `
              <div class="wbtm_seat_price_controls">
                <div class="wbtm_seat_price_field">
                  <input type="number" step="0.01" class="formControl wbtm_price_validation" 
                         name="wbtm_${seatKey}_price_adult[]" 
                         placeholder="Adult Price"
                         value="" />
                </div>
                <div class="wbtm_seat_price_field">
                  <input type="number" step="0.01" class="formControl wbtm_price_validation" 
                         name="wbtm_${seatKey}_price_child[]" 
                         placeholder="Child Price"
                         value="" />
                </div>
                <div class="wbtm_seat_price_field">
                  <input type="number" step="0.01" class="formControl wbtm_price_validation" 
                         name="wbtm_${seatKey}_price_infant[]" 
                         placeholder="Infant Price"
                         value="" />
                </div>
              </div>
            `;
            $container.append(priceControls);
          }
        }
      });
    } else {
      // Hide advanced features
      $('.wbtm_seat_container').removeClass('wbtm_advanced_features_enabled');
      $('.wbtm_seat_block_controls').hide();
      $('.wbtm_seat_price_controls').hide();
    }
  }
  
  // Initialize advanced features toggle when seat plan is regenerated
  $(document).on('DOMNodeInserted', '.wbtm_seat_plan_preview, .wbtm_seat_plan_preview_dd', function() {
    initializeRotationButtons();
    
    // Check if advanced features should be enabled
    let isAdvancedEnabled = $('input[name="wbtm_enable_advanced_seat_features"]').is(':checked');
    toggleAdvancedSeatFeatures(isAdvancedEnabled);
  });
})(jQuery);
