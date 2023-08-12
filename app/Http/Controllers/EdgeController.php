<?php

namespace App\Http\Controllers;

use App\Models\HumidityTemperature;
use App\Models\Node;
use App\Models\Sensor;
use App\Models\Soil;
use App\Models\Temp;
use App\Models\Threshold;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class EdgeController extends Controller
{
    public function soil(Request $request)
    {
        try {
            $request->validate(
                [
                    'sensor_id' => 'required',
                    'value' => 'required',
                ]
            );
            $i = 0;
            $tmp = [];
            $threshold = Threshold::first();
            try {
                foreach ($request->sensor_id as $soil) {
                    $data[] = [
                        'sensor_id' => $request['sensor_id'][$i],
                        'value' => $request['value'][$i],
                    ];
                    $temp = Temp::where('id_unique', $request['sensor_id'][$i])->first();
                    if ($temp == null) {
                        Temp::create(
                            [
                                'id_unique' => $request['sensor_id'][$i],
                                'value' => $request['value'][$i]
                            ]
                        );
                        $data[] = Soil::create(
                            [
                                'id_unique' => $request['sensor_id'][$i],
                                'value' => $request['value'][$i],
                                'status' => 'success',
                            ]
                        );
                    }
                    if (abs($request['value'][$i] - $temp->value) >= $threshold->soil_moisture) {
                        $tmp = abs($request['value'][$i] - $temp->value);
                        $temp->value = $request['value'][$i];
                        $temp->save();
                        $data[] = Soil::create(
                            [
                                'id_unique' => $request['sensor_id'][$i],
                                'value' => $request['value'][$i],
                                'status' => 'success',
                            ]
                        );
                    }
                    $i++;
                }
                // $client = new Client();
                // $client->post(
                //     'http://192.168.180.80:8001/api/edge/soil',
                //     [
                //         'form_params' => [$data]
                //     ]
                // );
                $response = Http::post('https://sipunggur.iotsiskom.com/api/edge/soil',
                [
                    $data
                ]);
                return response()->json($response->json(),200);
            } catch (Exception $e) {

                // foreach ($request->sensor_id as $soil) {
                //     $temp = Temp::where('id_unique', $request['sensor_id'][$i])->first();
                //     if ($temp == null) {
                //         Temp::create(
                //             [
                //                 'id_unique' => $request['sensor_id'][$i],
                //                 'value' => $request['value'][$i]
                //             ]
                //         );
                //         $data[] = Soil::create(
                //             [
                //                 'id_unique' => $request['sensor_id'][$i],
                //                 'value' => $request['value'][$i],
                //                 'status' => 'failed',
                //             ]
                //         );
                //     }
                //     if (abs($request['value'][$i] - $temp->value) >= $threshold->soil_moisture) {
                //         $tmp = abs($request['value'][$i] - $temp->value);
                //         $temp->value = $request['value'][$i];
                //         $temp->save();
                //         $data[] = Soil::create(
                //             [
                //                 'id_unique' => $request['sensor_id'][$i],
                //                 'value' => $request['value'][$i],
                //                 'status' => 'failed',
                //             ]
                //         );
                //     }
                //     $i++;
                // }
                return response()->json($e->getMessage(), 401);
            }
            return response()->json($tmp);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 401);
        }
    }
    public function humiditytemperature(Request $request)
    {
        $request->validate(
            [
                'sensor_id' => 'required',
                'value' => 'required',
            ]
        );
        $data[] = HumidityTemperature::create(
            [
                'id_unique' => $request->sensor_id,
                'humidity' => $request->humidity,
                'temperature' => $request->temperature,
            ]
        );
        return response()->json($data);
    }

    public function get(Request $request)
    {
        try {
            Artisan::call('migrate:fresh');
            $response = Http::get('https://sipunggur.iotsiskom.com/api/data');
            foreach ($response->object()->data->node as $node) {
                Node::create(
                    [
                        'id' => (int) $node->id,
                        'id_unique' => $node->id_unique,
                        'name' => (string) $node->name,

                    ]
                );
            }
            foreach ($response->object()->data->sensor as $sensor) {
                Sensor::create(
                    [

                        'id' => (int) $sensor->id,
                        'id_unique' => $sensor->id_unique,
                        'name' => (string) $sensor->name,
                        'node_id' => (int) $sensor->node_id,
                    ]
                );
            }

            return response()->json('success');
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 401);
        }
    }
}
