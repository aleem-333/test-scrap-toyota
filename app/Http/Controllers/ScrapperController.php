<?php

namespace App\Http\Controllers;

use DOMDocument;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Symfony\Component\DomCrawler\Crawler;

class ScrapperController extends Controller
{
    protected $client = '';

    public function __construct()
    {
        $this->client = new Client();
    }

    public function index()
    {
        set_time_limit(0);
        // URL to scrape
        $baseUrl = 'https://www.buyatoyota.com';
        $url = $baseUrl . '/greaterny/offers/?filters=lease&limit=10000000';

        try {
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);
            $offerUrls = $crawler->filter('a[href*="/offer-detail/"]')->each(function ($node) {
                return $node->attr('href');
            });
            $offerUrls = array_unique($offerUrls);
            $offerUrls = array_values($offerUrls);
            
            $finalData = [];
            foreach ($offerUrls as $offerUrl) {
                $finalData[] = $this->scrapOffer($baseUrl . $offerUrl);
            }
            
            
            // export to csv
            $filename = 'offers.csv';
            $handle = fopen($filename, 'w+');
            $keys = array_keys($finalData[0]);
            fputcsv($handle, $keys);
            foreach ($finalData as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
            // download file
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '";');
            readfile($filename);
            exit();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function scrapOffer($url = null)
    {
        try {
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);

            $regexList = [
                'trim' => '/:year :model ([\w\s.]+) Model/',
                'msrp' => '/Total SRP of \$([\d,.]+)/',
                'annual_miles' => '/([\d,]+) miles per year/',
                'acquisition_fee' => '/acquisition fee of \$([\d,.]+)/',
                'residual_value' => '/purchase amount of \$([\d,.]+)/',
                'capitalized_cost' => '/capitalized cost of \$([\d,.]+)/',
                'mileage_overage' => '/\$([\d,.]+) per mile for/',
                'disposition_fee' => '/\$([\d,.]+) disposition fee/',
                'end_date' => '/Expires (\d{2}-\d{2}-\d{4})/',
            ];

            $title = $crawler->filter('h1')->text();
            $title = explode(' ', $title);
            $price = $crawler->filter('.offer-dt-numberMain')->text();
            $price = (double)trim($price, '$');

            $pattern = '/Lease a (\d{4} \w+) for (\$\d+)/';
            $list = $crawler->filter('p')->each(function ($node) use ($pattern) {
                if (preg_match($pattern, $node->text(), $matches)) {
                    return $node->nextAll()->filter('ul')->text();
                } else {
                    return '';
                }
            });

            $list = array_values(array_filter($list));


            if ($list === []) {
                $trim = '';
            } else {
                $newRegex = str_replace(':year', $title[0], $regexList['trim']);
                $newRegex = str_replace(':model', $title[1], $newRegex);
                $trim = preg_match($newRegex, $list[0], $matches) ? $matches[1] : '';
            }
            

            $desclaimer = $crawler->filter('#disclaimerContent')->text();
            $msrp = (preg_match($regexList['msrp'], $desclaimer, $matches)) ? $matches[1] : '';
            $msrp = (double)str_replace(',', '', $msrp);

            $values = $crawler->filter('.offer-dt-number')->each(function ($node) {
                return $node->text();
            });

            $term = (int)$values[0];
            $due_at_signing = str_replace(',', '', $values[1]);
            $due_at_signing = (double)trim($due_at_signing, '$');

            $annual_miles = (preg_match($regexList['annual_miles'], $desclaimer, $matches)) ? $matches[1] : '';
            $annual_miles = (double)str_replace(',', '', $annual_miles);
            $acquisition_fee = (preg_match($regexList['acquisition_fee'], $desclaimer, $matches)) ? $matches[1] : '';
            $acquisition_fee = (double)str_replace(',', '', $acquisition_fee);
            $residual_value = (preg_match($regexList['residual_value'], $desclaimer, $matches)) ? $matches[1] : '';
            $residual_value =  (double)str_replace(',', '', $residual_value);
            $capitalized_cost = (preg_match($regexList['capitalized_cost'], $desclaimer, $matches)) ? $matches[1] : '';
            $capitalized_cost = (double)str_replace(',', '', $capitalized_cost);
            $mileage_overage = (preg_match($regexList['mileage_overage'], $desclaimer, $matches)) ? $matches[1] : '';
            $mileage_overage = (double)str_replace(',', '', $mileage_overage);
            $disposition_fee = (preg_match($regexList['disposition_fee'], $desclaimer, $matches)) ? $matches[1] : '';
            $disposition_fee = (double)str_replace(',', '', $disposition_fee);
            $end_date = (preg_match($regexList['end_date'], $desclaimer, $matches)) ? $matches[1] : '';

            $monthly_payment_zero = round($price + (($due_at_signing - $price) / $term), 2);
            $residual_perc = round(($residual_value / $msrp) * 100, 2);
            $money_factor = round(($price - (($capitalized_cost - $residual_value) / $term)) / ($capitalized_cost + $residual_value), 8);
            $interest_rate = round($money_factor * 2400, 1);


            // Scraping logic for getting offer details
            $offer = [
                'year' => $title[0],
                'make' => 'Toyota', // 'Toyota' is hardcoded
                'model' => $title[1],
                'trim' => $trim,
                'msrp' => $msrp,
                'monthly_payment' => $price,
                'monthly_payment_zero' => $monthly_payment_zero,
                'term' => $term,
                'due_at_signing' => $due_at_signing,
                'annual_miles' => $annual_miles,
                'acquisition_fee' => $acquisition_fee,
                'residual_value' => $residual_value,
                'residual_perc' => $residual_perc,
                'capitalized_cost' => $capitalized_cost,
                'money_factor' => $money_factor,
                'interest_rate' => $interest_rate,
                'mileage_overage' => $mileage_overage,
                'disposition_fee' => $disposition_fee,
                'end_date' => $end_date,
            ];
            // Return the scraped data
            return $offer;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
