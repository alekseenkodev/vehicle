<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;


class VehicleController extends Controller
{

    /**Get info about vehicle
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInfo(Request $request)
    {

        // get data from post json
        $post_json = $request->json()->all();

        $year = (isset($post_json['modelYear']) ? $post_json['modelYear'] : $request->year);
        $make = (isset($post_json['manufacturer']) ? $post_json['manufacturer'] : $request->manufacturer);
        $model = (isset($post_json['model']) ? $post_json['model'] : $request->model);

        //small validate
        if (trim($year) == '' || trim($make) == '' || trim($model) == '') {
            return response()->json(
                ['Count' => 0,
                    'Results' => []
                ]);
        }


        //http client Guzzle
        $client = new Client();
        $url = 'https://one.nhtsa.gov/webapi/api/SafetyRatings/modelyear/' . $year . '/make/' . $make . '/model/' . $model . '?format=json';

        //create http request for nhtsa api
        try {
            $res = $client->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-type' => 'application/json'
                ]]);
        } catch (RequestException $e) {
            //get bad answer for example "undefined"
            if ($e->getResponse()->getStatusCode() == '400') {
                return response()->json(
                    ['Count' => 0,
                        'Results' => []
                    ]);
            }
        }

        //get answer body
        $answer = json_decode($res->getBody()->getContents(), true);
        $result = [];

        //create response result
        foreach ($answer['Results'] as $item) {
            $inf['Description'] = $item['VehicleDescription'];
            $inf['VehicleId'] = $item['VehicleId'];
            $result[] = $inf;
        }

        //if we need crash rating
        if ($request->withRating == 'true') {

            $result = [];

            if (count($answer['Results']) > 0) {
                foreach ($answer['Results'] as $car) {
                    $add_url = 'https://one.nhtsa.gov/webapi/api/SafetyRatings/VehicleId/' . $car['VehicleId'] . '?format=json';

                    try {
                        $add_info = $client->request('GET', $add_url, [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Content-type' => 'application/json'
                            ]]);
                    } catch (RequestException $e) {
                        if ($e->getResponse()->getStatusCode() == '400') {
                            return response()->json(
                                ['Count' => 0,
                                    'Results' => []
                                ]);
                        }
                    }

                    $other_info = json_decode($add_info->getBody()->getContents(), true);
                    //create response result

                    $info['CrashRating'] = $other_info['Results'][0]['OverallRating'];
                    $info['Description'] = $car['VehicleDescription'];
                    $info['VehicleId'] = $car['VehicleId'];
                    $result[] = $info;

                }

            }
        }

        //return response
        return response()->json(
            ['Count' => $answer['Count'],
                'Results' => $result
            ]);

    }


}
