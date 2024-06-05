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
            url: mp_ajax_url,
            data: {
              action: "wbtm_reload_pricing",
              post_id: post_id,
              route_infos: route_infos,
            },
            beforeSend: function () {
              dLoader(target);
            },
            success: function (data) {
              target.html(data);
              //dLoaderRemove(parent);
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
    ".wbtm_settings_pricing_routing .wbtm_stop_item .mp_item_remove",
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
  $(document).on(
    "click",
    ".wbtm_settings_seat .wbtm_create_seat_plan",
    function (e) {
      let parent = $(this).closest(".wbtm_settings_seat");
      let target = parent.find(".wbtm_seat_plan_preview");
      let post_id = $('[name="wbtm_post_id"]').val();
      let row = parseInt(parent.find('[name="wbtm_seat_rows"]').val());
      let column = parseInt(parent.find('[name="wbtm_seat_cols"]').val());
      if (row > 0 && column > 0) {
        $.ajax({
          type: "POST",
          url: mp_ajax_url,
          data: {
            action: "wbtm_create_seat_plan",
            post_id: post_id,
            row: row,
            column: column,
          },
          beforeSend: function () {
            dLoader(target);
          },
          success: function (data) {
            parent.find('[name="wbtm_seat_cols_hidden"]').val(column);
            parent.find('[name="wbtm_seat_rows_hidden"]').val(row);
            target.html(data);
            //dLoaderRemove(parent);
          },
          error: function (response) {
            console.log(response);
          },
        });
      } else {
        alert("Number  of row & column must be greater than 0");
      }
    }
  );
  $(document).on(
    "click",
    ".wbtm_settings_seat .wbtm_seat_plan_preview .mp_item_remove",
    function (e) {
      if (e.result) {
        let parent = $(".wbtm_settings_seat");
        let target = parent.find('[name="wbtm_seat_rows"]');
        let value = parseInt(target.val()) - 1;
        target.val(value);
        parent.find('[name="wbtm_seat_rows_hidden"]').val(value);
      }
    }
  );
  $(document).on(
    "click",
    ".wbtm_settings_seat .wbtm_seat_plan_preview .mp_add_item",
    function (e) {
      if (e.result) {
        let parent = $(".wbtm_settings_seat");
        let target = parent.find('[name="wbtm_seat_rows"]');
        let value = parseInt(target.val()) + 1;
        target.val(value);
        parent.find('[name="wbtm_seat_rows_hidden"]').val(value);
      }
    }
  );
  //=============================//
  $(document).on(
    "click",
    ".wbtm_settings_seat .wbtm_create_seat_plan_dd",
    function (e) {
      let parent = $(this).closest(".wbtm_settings_seat");
      let target = parent.find(".wbtm_seat_plan_preview_dd");
      let post_id = $('[name="wbtm_post_id"]').val();
      let row = parseInt(parent.find('[name="wbtm_seat_rows_dd"]').val());
      let column = parseInt(parent.find('[name="wbtm_seat_cols_dd"]').val());
      if (row > 0 && column > 0) {
        $.ajax({
          type: "POST",
          url: mp_ajax_url,
          data: {
            action: "wbtm_create_seat_plan_dd",
            post_id: post_id,
            row: row,
            column: column,
          },
          beforeSend: function () {
            dLoader(target);
          },
          success: function (data) {
            parent.find('[name="wbtm_seat_cols_dd_hidden"]').val(column);
            parent.find('[name="wbtm_seat_rows_dd_hidden"]').val(row);
            target.html(data);
            //dLoaderRemove(parent);
          },
          error: function (response) {
            console.log(response);
          },
        });
      } else {
        alert("Number  of row & column must be greater than 0");
      }
    }
  );
  $(document).on(
    "click",
    ".wbtm_settings_seat .wbtm_seat_plan_preview_dd .mp_item_remove",
    function (e) {
      if (e.result) {
        let parent = $(".wbtm_settings_seat");
        let target = parent.find('[name="wbtm_seat_rows_dd"]');
        let value = parseInt(target.val()) - 1;
        target.val(value);
        parent.find('[name="wbtm_seat_rows_dd_hidden"]').val(value);
      }
    }
  );
  $(document).on(
    "click",
    ".wbtm_settings_seat .wbtm_seat_plan_preview_dd .mp_add_item",
    function (e) {
      if (e.result) {
        let parent = $(".wbtm_settings_seat");
        let target = parent.find('[name="wbtm_seat_rows_dd"]');
        let value = parseInt(target.val()) + 1;
        target.val(value);
        parent.find('[name="wbtm_seat_rows_dd_hidden"]').val(value);
      }
    }
  );
})(jQuery);
//==========Pickup settings=================//
(function ($) {
  "use strict";
  $(document).on("click", ".wbtm_add_group_pickup", function () {
    let parent = $(this).closest(".mp_settings_area");
    let target_item = $(this)
      .next($(".mp_hidden_content"))
      .find(" .mp_hidden_item");
    let item = target_item.html();
    load_sortable_datepicker(parent, item);
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
    let parent = $(this).closest(".mp_settings_area");
    let target_item = $(this)
      .next($(".mp_hidden_content"))
      .find(" .mp_hidden_item");
    let item = target_item.html();
    load_sortable_datepicker(parent, item);
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
