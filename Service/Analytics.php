<?php

namespace GoogleBundle\Service;


use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Google_Client;
use Google_Service_AnalyticsReporting;
use Google_Service_AnalyticsReporting_DateRange;
use Google_Service_AnalyticsReporting_Dimension;
use Google_Service_AnalyticsReporting_DimensionFilter;
use Google_Service_AnalyticsReporting_DimensionFilterClause;
use Google_Service_AnalyticsReporting_GetReportsRequest;
use Google_Service_AnalyticsReporting_GetReportsResponse;
use Google_Service_AnalyticsReporting_Metric;
use Google_Service_AnalyticsReporting_ReportRequest;
use GoogleBundle\Exception\AnalyticsException;
use Symfony\Component\VarDumper\VarDumper;

class Analytics
{

    public $kernel_root;
    public $json_file_path;
    public $view_id;

    protected $analytics;

    /** @var Google_Service_AnalyticsReporting_DateRange[]|null */
    protected $date_ranges = [];
    /** @var Google_Service_AnalyticsReporting_Dimension[]|null */
    protected $dimensions = [];
    /** @var Google_Service_AnalyticsReporting_DimensionFilter[]|null */
    protected $dimension_filters = [];
    /** @var Google_Service_AnalyticsReporting_DimensionFilterClause[]|null */
    protected $dimension_filter_clauses = [];
    /** @var Google_Service_AnalyticsReporting_Metric[]|null */
    protected $metrics = [];

    protected $response = null;
    /** @var Google_Service_AnalyticsReporting_GetReportsResponse */
    protected $request_result = null;

    public function __construct($json_file_path = null, $view_id = null)
    {
        $this->json_file_path = $json_file_path;
        $this->view_id = $view_id;

        $this->initializeAnalytics();
    }

    /**
     * Initializes an Analytics Reporting API V4 service object.
     *
     * @return void An authorized Analytics Reporting API V4 service object.
     * @throws \Google_Exception
     */
    public function initializeAnalytics()
    {
        $client = new Google_Client();
        $client->setApplicationName("Pkshetlie Google Analytics Reporting");
        $client->setAuthConfig($this->json_file_path);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        $this->analytics = new Google_Service_AnalyticsReporting($client);
    }

    /**
     * @param DateTime $start
     * @param DateTime $end
     * @return Analytics
     * @throws AnalyticsException
     */
    public function addDateRange(DateTime $start, DateTime $end)
    {
        if (count($this->date_ranges) == 2) {
            throw new AnalyticsException("Vous avez atteint le nombre maximum de date_range autorisé : 2");
        }

        $date_range = new Google_Service_AnalyticsReporting_DateRange();
        $date_range->setStartDate($start->format('Y-m-d'));
        $date_range->setEndDate($end->format('Y-m-d'));
        $this->date_ranges[] = $date_range;
        return $this;
    }

    /**
     * @param DateTime $start
     * @param DateTime $end
     * @return Analytics
     */
    public function setDateRange(DateTime $start, DateTime $end)
    {
        $date_range = new Google_Service_AnalyticsReporting_DateRange();
        $date_range->setStartDate($start->format('Y-m-d'));
        $date_range->setEndDate($end->format('Y-m-d'));
        $this->date_ranges = [$date_range];
        return $this;
    }

    /**
     * @return Analytics
     * @throws AnalyticsException
     */
    public function nextDay()
    {
        $this->addDays(1);
        return $this;
    }

    /**
     * @param $days number of days to add
     * @return Analytics
     * @throws AnalyticsException
     */
    public function addDays($days)
    {
        if (count($this->date_ranges) !== 1) {
            throw new AnalyticsException("Cette methode ne fonctionne que lorsqu'il n'y a que 1 daterange défini");
        }

        $date_range = $this->date_ranges[0];
        $start = DateTime::createFromFormat('Y-m-d', $date_range->getStartDate())->modify('+' . $days . ' days');
        $end = DateTime::createFromFormat('Y-m-d', $date_range->getEndDate())->modify('+' . $days . ' days');

        $date_range->setStartDate($start->format('Y-m-d'));
        $date_range->setEndDate($end->format('Y-m-d'));
        $this->date_ranges = [$date_range];

        return $this;
    }

    /**
     * @param $days number of days to add
     * @return Analytics
     * @throws AnalyticsException
     */
    public function subDays($days)
    {
        if (count($this->date_ranges) !== 1) {
            throw new AnalyticsException("Cette methode ne fonctionne que lorsqu'il n'y a que 1 daterange défini");
        }

        $date_range = $this->date_ranges[0];
        $start = DateTime::createFromFormat('Y-m-d', $date_range->getStartDate())->modify('-' . $days . ' days');
        $end = DateTime::createFromFormat('Y-m-d', $date_range->getEndDate())->modify('-' . $days . ' days');

        $date_range->setStartDate($start->format('Y-m-d'));
        $date_range->setEndDate($end->format('Y-m-d'));
        $this->date_ranges = [$date_range];

        return $this;
    }

    /**
     * @return Analytics
     * @throws AnalyticsException
     */
    public function previousDay()
    {
        $this->subDays(1);

        return $this;
    }


    /**
     * preset pour ga:pagePath
     */
    public function addDimPagePath()
    {
        $dimension = new Google_Service_AnalyticsReporting_Dimension();
        $dimension->setName('ga:pagePath');
        $this->dimensions[] = ($dimension);
    }

    public function searchForPage($path, $exactPath = true)
    {
        if ($exactPath) {
            $path = '^' . $path . '$';
        }

        $dimension = new Google_Service_AnalyticsReporting_Dimension();
        $dimension->setName('ga:pagePath');
        $this->dimensions[] = ($dimension);

        $dimension_filter = new Google_Service_AnalyticsReporting_DimensionFilter();
        $dimension_filter->setDimensionName('ga:pagePath');
        $dimension_filter->setExpressions($path);
        $this->dimension_filters[] = ($dimension_filter);

        $dimension_clause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
        $dimension_clause->setFilters($this->dimension_filters);
        $this->dimension_filter_clauses[] = ($dimension_clause);

        $metrics = new Google_Service_AnalyticsReporting_Metric();
        $metrics->setExpression("ga:uniquePageViews");
        $metrics->setAlias("pages unique vues");
        $this->metrics[] = ($metrics);

        $metrics = new Google_Service_AnalyticsReporting_Metric();
        $metrics->setExpression("ga:pageviews");
        $metrics->setAlias("pages vues");
        $this->metrics[] = ($metrics);

        return $this;
    }

    /**
     * @return \Google_Service_AnalyticsReporting_Report
     */
    public function getReports()
    {
        return $this->getResults()->getReports();
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->getResults()->getReports()[0]->getData()[0]->values;
    }

    /**
     * @return Google_Service_AnalyticsReporting_GetReportsResponse
     */
    public function getResults()
    {
        return $this->request_result;
    }

    /**
     * @return $this
     */
    public function doRequest()
    {
        $reportRequest = new Google_Service_AnalyticsReporting_ReportRequest();
        $reportRequest->setViewId($this->view_id);

        $reportRequest->setDateRanges($this->date_ranges);

        $reportRequest->setMetrics($this->metrics);
        $reportRequest->setDimensions($this->dimensions);
        $reportRequest->setDimensionFilterClauses($this->dimension_filter_clauses);

        $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
        $body->setReportRequests(array($reportRequest));
        $this->request_result = $this->analytics->reports->batchGet($body);

        return $this;
    }


    public function setDimension($name)
    {
        if (!preg_match('#^ga\:(.*)#isU', $name)) {
            $name = 'ga:' . $name;
        }
        $dimension = new Google_Service_AnalyticsReporting_Dimension();
        $dimension->setName($name);
        $this->dimensions[] = ($dimension);

        return $this;
    }


    /**
     * Parses and prints the Analytics Reporting API V4 response.
     *
     */
    public function printResults()
    {
        for ($reportIndex = 0; $reportIndex < count($this->request_result); $reportIndex++) {
            $report = $this->request_result[$reportIndex];
            $header = $report->getColumnHeader();
            $dimensionHeaders = $header->getDimensions();
            $metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();
            $rows = $report->getData()->getRows();
            for ($rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
                $row = $rows[$rowIndex];
                $dimensions = $row->getDimensions();
                $metrics = $row->getMetrics();
                for ($i = 0; $i < count($dimensionHeaders) && $i < count($dimensions); $i++) {
                    print($dimensionHeaders[$i] . ": " . $dimensions[$i] . "\n");
                }

                for ($j = 0; $j < count($metrics); $j++) {
                    $values = $metrics[$j]->getValues();
                    for ($k = 0; $k < count($values); $k++) {
                        $entry = $metricHeaders[$k];
                        print($entry->getName() . ": " . $values[$k] . "\n");
                    }
                }
            }
        }
    }
}