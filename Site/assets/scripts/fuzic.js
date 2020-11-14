var multibox_timeout;
var multibox_box;
var blink_timeout;
var blink_interval = 0;
var event_update = false;

$(document).ready(function() {
  $('.card').waypoint(function(direction) {
    if(direction == 'down') {
      $(this).clone().addClass('sticky').appendTo('#main').animate({ top: parseInt($('#top-bar').css('border-top-width')) }, 200);
    }
  }, { offset: function() { return 97 - $(this).height(); } });

  $('.card').waypoint(function(direction) {
    if(direction == 'up') {
      $('.sticky').animate({top: '-15px'}, 200, function() { $(this).remove(); });
    }
  }, { offset: function() { return 100 - $(this).height(); } });

  if(!$('#tooltip')) {
    $('<div>').appendTo('body').attr('id', 'tooltip');
    $('#tooltip').css('position', 'absolute');
  }
  $('.tooltippable[title], .tooltippable *[title]').mouseover(function() {
    $('<p/>').attr('id', 'tooltip').html($(this).attr('title')).appendTo('body');
    $(this).addClass('tooltipped').data('temp-title', $(this).attr('title')).attr('title', '');
    $('body').mousemove(function(e) {
      $('#tooltip').css('left', e.pageX+15+'px').css('top', e.pageY-25+'px');
    });
    return false;
  });
  $('.tooltippable').mouseout(function() {
    $('#tooltip').remove();
    $('.tooltipped').attr('title', $('.tooltipped').data('temp-title')).removeClass('tooltipped').data('temp-title', false);
  });
  $('.calendar').each(function() {
    if($(this).find('.current').size() == 0) {
      $(this).hide();
    }
    var year = parseInt($(this).attr('id').split('-')[1]);
    if($('#year-' + (year + 1)).size() == 0) {
      $(this).find('.next').hide();
    }
    if($('#year-' + (year - 1)).size() == 0) {
      $(this).find('.prev').hide();
    }
    $(this).find('.next, .prev').on('click', function(e) {
      e.preventDefault();
      var inc = $(this).hasClass('next') ? 1 : -1;
      var year = parseInt($(this).parents('nav').attr('id').split('-')[1]);
      $(this).parents('nav').hide();
      $('#year-' + (year + inc)).show();
    });
  });

  $('.multibox').each(function() {
    var name = $(this).attr('name');
    $(this).attr('name', name + '-label');
    $('<input type="hidden">').attr('name', name).appendTo($(this).parents('form'));
  });

  $('.multibox').on('keyup', function() {
    clearInterval(multibox_timeout);
    multibox_box = this;
    multibox_timeout = setTimeout(function() {
      box = multibox_box;
      $('#' + $(box).attr('name').split('-label')[0]).val('');
      $('#multibox-items').remove();

      if($(box).val() != '') {
        var position = $(box).offset();
        var height = $(box).outerHeight();
        var owner_id = $(box).attr('name').split('-label')[0];
        var query = $(box).val();

        $.get('/async/events/search/?filter=' + escape(query), function(items) {
          var ul = $('<ul>').attr('id', 'multibox-items').css('top', (position.top + height) + 'px').css('left', position.left + 'px').attr('data-owner', owner_id);
          for(id in items) {
            var li = $('<li>').html(items[id]).attr('id', 'multibox-option-' + id).appendTo(ul);
            $(li).on('mousedown', function(e) {
              e.preventDefault();

              var owner_id = $(this).parents('ul').attr('data-owner');
              $('input[name=' + owner_id + ']').val($(this).attr('id').split('-option-')[1]);
              $('input[name=' + owner_id + '-label]').val($(this).text());

              $('#multibox-items').remove();
            });
          }
          ul.appendTo('body');
        });
      }
    }, 200);
  });

  $('.multibox').on('blur', function() {
    $('#multibox-items').remove();
  });

  $('form[data-url]').on('submit', function(e) {
    e.preventDefault();

    var url = $(this).attr('data-url').replace(/\[([^}]+)\]/, function(field) {
      var field_name = field.substring(1, (field.length - 1));
      if($('input[name=' + field_name + ']')) {
        return $('input[name=' + field_name + ']').val();
      } else {
        return field;
      }
    });

    if(url.slice(-2) == '//') {
      blink_timeout = setInterval(function() {
        if(blink_interval > 5) {
          blink_interval = 0;
          clearInterval(blink_timeout);
        } else {
          if(blink_interval % 2 == 0) {
            $('input[name=query-label]').css('background-color', $('#site-header-wrap').css('background-color'));
            $('input[name=query-label]').css('color', '#FFFFFF');
          } else {
            $('input[name=query-label]').css('background-color', '#FFFFFF');
            $('input[name=query-label]').css('color', '#000000');
          }
        }
        blink_interval += 1;
      }, 100);
    } else {
      document.location = url;
    }
  });

  $('#select-timezone').on('change', function() {
    var timezone = $(this).val();
    var desc = $.trim($("#select-timezone option[value='" + timezone + "']").text().split(' - ')[0].replace('UTC', '+00:00'));
    var offsetbits = desc.split(':');
    var offset = (parseInt(offsetbits[0]) * 3600) + (parseInt(offsetbits[1]) * 60);
    $.cookie('timezone', $(this).val(), { path: '/', expires: 365 });
    $.cookie('timezoneoffset', offset, { path: '/', expires: 365 });
    document.location.href = document.location.href + "";
  });
  $('#timezone-wrapper').css('display', 'block');

  if($('li.live-notice')) {
    event_counter = setInterval(function() {
      var current = parseInt($('#update-countdown').html());
      if(current == 0) {
        $('.update-countdown').html('61');
      } else {
        $('.update-countdown').html(current - 1);
      }
    }, 1000);
    event_updater = setInterval(function() {
      $.get(document.location.href+'ajax/', function(data) {
        if(!data['is_live']) {
          clearInterval(event_updater);
          $('li.live-notice').remove();
        }
        $('.event-average').html(data['average']);
        $('.event-peak').html(data['peak']);
        $('.event-vh').html(data['vh']);
        $('.event-time').html(data['time']);
        $('.event-month-rank a').html(data['rank_month']);
        $('.event-alltime-rank a').html(data['rank_overall']);
        for(i in data['streams']) {
          $('#stream-' + i + ' .peak').html(data['streams'][i]['peak']);
          $('#stream-' + i + ' .average').html(data['streams'][i]['average']);
          $('#stream-' + i + ' .vh').html(data['streams'][i]['vh']);
          $('#stream-' + i + ' .time').html(data['streams'][i]['time']);
        }
        //charts.update_chart($('.make-chart').highcharts())(data['chart']);
      });
    }, 61000);
  }
});

function pad(number) {
  return (number.toString().length < 2) ? '0' + number : number;
}

function makeUTCDate(seconds) {
  d = new Date(seconds * 1000);
  utc = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate(), d.getUTCHours(), d.getUTCMinutes(), d.getUTCSeconds(), d.getUTCMilliseconds()));
  return utc;
}

String.prototype.ucfirst = function() {
  return this.charAt(0).toUpperCase() + this.slice(1);
}

color = {
  clamp: function(value) {
    if(value >= 0 && value <= 255) {
      return value;
    } else if(value < 0) {
      return 0;
    } else {
      return 255;
    }
  },

  darken: function(c, factor) {
    factor = parseFloat(factor);
    if(c.substring(0, 1) == '#') {
      var red = parseInt(c.substring(1,3), 16);
      var green = parseInt(c.substring(3,5), 16);
      var blue = parseInt(c.substring(5,7), 16);
    } else {
      var components = c.split('(')[1].split(')')[0].split(',');
      var red = parseInt(components[0]);
      var green = parseInt(components[1]);
      var blue = parseInt(components[2]);
    }

    red = color.clamp(Math.floor(red * factor));
    green = color.clamp(Math.floor(green * factor));
    blue = color.clamp(Math.floor(blue * factor));

    ret = '#' + pad(red.toString(16)) + pad(green.toString(16)) + pad(blue.toString(16));
    return ret;
  },

  lighten: function(c, factor) {
    factor = parseFloat(factor);
    if(c.substring(0, 1) == '#') {
      var red = parseInt(c.substring(1,3), 16);
      var green = parseInt(c.substring(3,5), 16);
      var blue = parseInt(c.substring(5,7), 16);
    } else {
      var components = c.split('(')[1].split(')')[0].split(',');
      var red = parseInt(components[0]);
      var green = parseInt(components[1]);
      var blue = parseInt(components[2]);
    }

    offset = 256 - parseInt(256 / factor);
    factor = (offset == 0) ? 1 : parseInt(256 / offset);

    red = color.clamp(offset + Math.floor(red / factor));
    green = color.clamp(offset + Math.floor(green / factor));
    blue = color.clamp(offset + Math.floor(blue / factor));

    ret = '#' + pad(red.toString(16)) + pad(green.toString(16)) + pad(blue.toString(16));
    return ret;
  },

  transparent: function(c, factor) {
    if(c.substring(0, 1) == '#') {
      var red = parseInt(c.substring(1,2), 16);
      var green = parseInt(c.substring(3,2), 16);
      var blue = parseInt(c.substring(5,2), 16);
    } else {
      var components = c.split('(')[1].split(')')[0].split(',');
      var red = parseInt(components[0]);
      var green = parseInt(components[1]);
      var blue = parseInt(components[2]);
    }

    return 'rgba(' + red + ', ' + green + ', ' + blue + ', ' + factor + ')';
  }
}