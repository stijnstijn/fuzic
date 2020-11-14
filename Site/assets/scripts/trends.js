var interval;
var chart_type;
var globalColorSeries = [];
var globalColorIndex = 0;

charts = {
  init: function() {
    charts.add_charts();
  },
  
  enable_links: function() {
    $('.chart-controls li a').on('click', function(e) {
      e.preventDefault();
      if($(this).parents('li').hasClass('current')) {
        return false;
      }
      $(this).parents('ul').find('li').removeClass('current');
      $(this).parent().addClass('current');
      type = $(this).attr('href').split('&')[1].split('=')[1];
      chart = $(this).parents('.controllable-chart').find('.make-chart').highcharts();
      chart.showLoading('Loading chart...');
      var per = $(this).parents('.controllable-chart').attr('data-per');
      $.get(document.location.href.split('?')[0] + 'chart/' + per + '/' + type + '/?async=1', charts.update_chart(chart), 'json');
    });
  },
  
  condense_labels: function(labels) {
    interval = Math.ceil(labels.length / 10);
    ret = [];
    for(i in labels) {
      if(i % interval == 0) {
        ret.push(labels[i]);
      } else {
        ret.push('');
      }
    }
    return ret;
  },
  
  axis_labels: function() {
    var per = this.chart.userOptions.per;
    if (per && per == 'month') {
      return this.value.substring(0, 3) + " '" + this.value.substring((this.value.length - 2), this.value.length);
    } else if (per && per == 'year') {
      return this.value;
    } else if(chart_type == 'rank') {
  		return charts.thousands(this.value);
  	} else if(chart_type == 'session') {
  		return charts.time(this.value);
  	} else {
  		return $.formatDateTime('d MM hh:ii', makeUTCDate(parseInt(this.value)));
  	}
  },
  
  update_chart: function(chart) {
    return function(data) {
      lowest = parseInt(Math.min.apply(Math,data.data) * 0.80);
      highest = parseInt(Math.max.apply(Math,data.data) * 1.20);
      if(lowest == 0 && highest == 0) {
        charts.hide(chart);
        return false;
      }
      chart.xAxis[0].labels = data.labels;
      chart.axes[1].options.min = lowest;
      chart.axes[1].options.max = highest;
      chart.series[0].name = data.type;
      chart.series[0].setData(data.data);
      chart.redraw();
      chart.hideLoading();
    }
  },
  
  hide: function(chart) {
    chart.series[0].setData([]);
    chart.showLoading('No recent data');
    chart.redraw();
  },
  
  time: function(time) {
    if(time < 60) {
      return '00:00:' + pad(time);
    }
    if(time < 3600) {
      m = Math.floor(time / 60);
      return '00:' + pad(m) + ':00';//' + pad(time - (m * 60));
    }
    h = Math.floor(time / 3600);
    m = Math.floor((time - (h * 3600)) / 60);
    return pad(h) + ':' + pad(m) + ':00'; //' + pad(time - (m * 60) - (h * 3600));
  },
  
  thousands: function(number, decimals) {
    // http://kevin.vanzonneveld.net
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    var n = !isFinite(+number) ? 0 : +number,
      prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
      s = '',
      toFixedFix = function (n, prec) {
        var k = Math.pow(10, prec);
        return '' + Math.round(n * k) / k;
      };
    // Fix for IE parseFloat(0.55).toFixed(0) = 0;
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {
      s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, ',');
    }
    if ((s[1] || '').length < prec) {
      s[1] = s[1] || '';
      s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    return s.join('.');
  },
  
  tooltip: function () {
    var per = $('.controllable-chart').attr('data-per');
    if(chart_type == 'event') {
      var streams = '';
      var offset = 0;
      total = 0;
      var s = '<b>' + $.formatDateTime('d MM hh:ii', makeUTCDate(parseInt(this.x) + offset)) + '</b><br>';
      if(this.points.length > 1) {
        $.each(this.points, function(i, point) {
          streams += '<span style="color:' + charts.getColorSeries(point.series.name) + '">' + point.series.name + '</span>: <b>' + (point.y>0?charts.thousands(point.y):'-') + '</b><br>';
          total += point.y
        });
        s += '<span>Total:</span> <b>' + charts.thousands(total) + '</b><br> <br>';
        return s + streams
      } else {
        s += '<span>Viewers:</span> <b>' + ((this.y > 0)?charts.thousands(this.y):'-') + '</b>';
        return s;
      }
    } else if(chart_type == 'session') {
      return '<b>' + charts.time(this.x) + '</b><br><span>Viewers: <b>' + ((this.y > 0)?charts.thousands(this.y):'-') + '</b>';
    } else {
      var value = (this.points[0].series.name == 'Time') ? charts.time(this.y) : charts.thousands(this.y);
      var name = (this.points[0].series.name == 'vh') ? 'VxH' : this.points[0].series.name.ucfirst();
      if((per && per == 'month') || $('.controllable-chart').hasClass('trend')) {
        return '<b>' + this.x + '</b><br><span>' + name + ': <b>' + ((this.y > 0)?value:'-') + '</b>';
      } else {
        return '<b>Week ' + this.x + '</b><br><span>' + name + ': <b>' + ((this.y > 0)?value:'-') + '</b>';
      }
    }
  },
  
  add_charts: function() {
    charts.set_colors();

    $('.make-chart').each(function() {
      session_chart = $(this).hasClass('session');
      var data = $.parseJSON($(this).attr('data-chart'));
      var labels = $.parseJSON($(this).attr('data-labels'));
      var unit = $(this).attr('data-label');
      var point = 1;
      if(!unit) unit = 'Viewers';
      
      //series
      if($(this).hasClass('multiple')) {
        chart_type = 'event';
        var legend = $.parseJSON($(this).attr('data-legend'));
        var series = [];
        var sequences = [];
        for(i in data) {
          for(j in data[i]) {
            if(!sequences[j]) sequences[j] = [];
            sequences[j].push(data[i][j]);
          }
        }
        for(i in sequences) {
          series.push({ name: legend[i], data: sequences[i] });
        }
        var thickness = 3;
        var stacked = true;
      } else {
        series = [{ name: unit, data: data }];
        var lowest = parseInt(Math.min.apply(Math,data) * 0.80);
        var highest = parseInt(Math.max.apply(Math,data) * 1.20);
        var thickness = 6;
        var stacked = false;
        chart_type = 'rank';
      }
      
      if(data.length > 15 || chart_type != 'rank') {
        interval = Math.ceil(data.length / 6);
      } else {
        interval = 1;
      }

      //init
      var per = $(this).parent().attr('data-per');
      console.log(per);
      $(this).data('chart', $(this).highcharts({
        credits: { enabled: false },
        chart: { type: 'column' },
        colors: charts.colors,
        title: { text: '' },
        per: per,
        xAxis: { type: 'datetime', categories: labels, showFirstLabel: false, labels: { maxStaggerLines: 1, step: interval, formatter: charts.axis_labels }, title: { enabled: false }, tickLength: 0, showLastLabel: true},
        yAxis: { min: lowest, max: highest, startOnTick: false, endOnTick: false, labels: { enabled: false }, title: { enabled: false }, tickLength: 0, gridLineWidth: 0 },
        tooltip: { shared: true, formatter: charts.tooltip },
        loading: { labelStyle: { fontFamily: 'sans-serif', top: '45%', color: '#000', textAlign: 'center', border: '1px solid ' + color.darken(charts.colors[0], 1.2), padding: '4px', color: '#FFF', background: charts.colors[0], fontSize: '16px' } },
        legend: { enabled: false },
        plotOptions: {
          column: { pointPadding: 0, groupPadding: 0, borderRadius: 4 },
          spline: { stacking: 'normal', lineWidth: thickness, fillOpacity: 0.6, marker: { radius: 0 } },
          series: { dataLabels: {
            enabled: true,
            formatter: function() {
              point += 1;
              if(this.point.y != 0 && this.point.x % interval == 0) {
                if(stacked) {
                  if(point < data.length) {
                    return charts.thousands(this.total);
                  }
                } else {
                  return (this.series.name == 'Time') ? charts.time(this.point.y) : charts.thousands(this.point.y);
                }
              }
            },
            backgroundColor: color.transparent(charts.colors[0], 0.65),
            borderColor: color.darken(charts.colors[0], 1.5),
            color: '#FFF',
            y: 25
          } } },
        series: series
      }));
      if(lowest == 0 && highest == 0) {
        charts.hide($(this).highcharts());
      }
    });
  
    charts.enable_links();
  },
  
  getColor: function(reset) {
    newColor = charts.colors[globalColorIndex];
    if(globalColorIndex >= (charts.colors.length - 1) || reset) {
      globalColorIndex = 0;
    } else {
      globalColorIndex++;
    }
    return newColor;
  },
  
  getColorSeries: function(series) {
    if(!globalColorSeries[series]) {
      globalColorSeries[series] = charts.getColor()
    }
    return globalColorSeries[series]
  },
  
  set_colors: function() {
    var base = $('#site-header-wrap').css('background-color');
    
    charts.colors = [];
    for(i = 0; i < 20; i += 1) {
      charts.colors.push(base);
      base = color.darken(base, 0.8);
    }
  }
};

$(document).ready(function() {
  charts.init();
});