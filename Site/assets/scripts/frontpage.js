var labels;

$(document).ready(function() {
  var labels = $.parseJSON($('#frontpage-panel .chart').attr('data-times'));
  var viewers = $.parseJSON($('#frontpage-panel .chart').attr('data-viewers'));
  var interval = 5000;
  var series = [{
    name: 'Viewers',
    data: viewers
  }];
  if($(this).find('.highlights')) {
    highlights = [];
    $(this).find('.highlights tr').each(function() {
      highlights.push({
        name: $(this).find('td').eq(2).find('a').text(),
        url: $(this).find('td').eq(2).find('a').attr('href'),
        x: parseInt($(this).find('td').eq(0).text()),
        y: parseInt($(this).find('td').eq(1).text()),
        z: $(this).find('td').eq(3).text(),
      });
    });
    series.push({name: 'highlights', type: 'scatter', color: '#999', marker: { enabled: true, symbol: "url(/assets/images/icon-event.png)", radius: 5 }, data: highlights});
  };
  $('#frontpage-panel .chart').highcharts({
    credits: { enabled: false },
    chart: { backgroundColor: $('#site-header-wrap').css('background-color'), type: 'areaspline', marginTop: 0, marginLeft: -5, marginBottom: 0, marginRight: -5, width: 622, height: 136 },
    colors: ['#FFFFFF'],
    title: { text: '' },
    exporting: { enabled: false },
    xAxis: { labels: { enabled: false }, categories: { enabled: false }, lineWidth: 0, gridLineWidth: 0, minorGridLineWidth: 0, tickWidth: 0, minorTickInterval: interval, tickInterval: interval, tickPixelInterval: interval },
    yAxis: { labels: { enabled: false }, categories: { enabled: false }, title: { text: null },  minorGridLineWidth: 0, gridLineWidth: 0, tickWidth: 0 , minorTickInterval: interval, tickInterval: interval, tickPixelInterval: interval },
    tooltip: {
      formatter: function () {
        if(this.series.name != 'highlights') {
          return '<span>' + labels[this.point.x] + '</span><br>' + (this.point.y==0?'No reported':'<b>'+this.point.y.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")+'</b>') + ' viewers';
        } else {
          return '<span>' + this.point.z + '</span><br><b>' + this.point.name + '</b>';
        }
      }
    },
    legend: { enabled: false },
    plotOptions: { areaspline: { lineWidth: 3, marker: { enabled: false } }, series: { point: { events: { click: function() { if (this.hasOwnProperty('url')) window.location.href = this.url; } } }, }, },
    series: series
  });
});