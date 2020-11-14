<h1><i class="fa fa-bar-chart-o"></i> Trends</h1>

<p class="intro">Note: Fuzic has only been tracking {$__game.name|e} streams since {$first_crawl.month|month} {$first_crawl.year}. Data from before this period is not included. Thus data for {$first_crawl.year} may be off, as well as data for {date('Y')}, which is of course still unfinished.</p>
<hr>

<section>
    <h3><i class="fa fa-calendar"></i> Per month</h3>

    <article class="controllable-chart trend" data-per="{$per}">
        <div class="chart-controls">
            <h4>Show:</h4>
            <ul>
                <li{if !isset($__get.chart_type) || (isset($__get.chart_type) && $__get.chart_type == 'average')} class="current"{/if}><a href="?chart={$id}&amp;chart_type=average"><i class="fa fa-arrow-right"></i> Average</a></li>
                <li{if isset($__get.chart_type) && $__get.chart_type == 'peak'} class="current"{/if}><a href="?chart={$id}&amp;chart_type=peak"><i class="fa fa-arrow-right"></i> Peak</a></li>
                <li{if isset($__get.chart_type) && $__get.chart_type == 'vh'} class="current"{/if}><a href="?chart={$id}&amp;chart_type=vh"><i class="fa fa-arrow-right"></i> Viewers &times; Hours</a></li>
            </ul>
        </div>
        <div class="make-chart" {$chart_month|highcharts}></div>
    </article>

    <h3><i class="fa fa-calendar"></i> Per year</h3>

    <article class="controllable-chart trend" data-per="year">
        <div class="chart-controls">
            <h4>Show:</h4>
            <ul>
                <li{if !isset($__get.chart_type) || (isset($__get.chart_type) && $__get.chart_type == 'average')} class="current"{/if}><a href="?chart={$id}&amp;chart_type=average"><i class="fa fa-arrow-right"></i> Average</a></li>
                <li{if isset($__get.chart_type) && $__get.chart_type == 'peak'} class="current"{/if}><a href="?chart={$id}&amp;chart_type=peak"><i class="fa fa-arrow-right"></i> Peak</a></li>
                <li{if isset($__get.chart_type) && $__get.chart_type == 'vh'} class="current"{/if}><a href="?chart={$id}&amp;chart_type=vh"><i class="fa fa-arrow-right"></i> Viewers &times; Hours</a></li>
            </ul>
        </div>
        <div class="make-chart" {$chart_year|highcharts}></div>
    </article>
</section>