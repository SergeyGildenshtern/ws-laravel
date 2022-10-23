<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Nette\Utils\Random;
use Illuminate\Support\Facades\Cookie;
use Validator;

class ApiController extends Controller
{
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            "first_name" => "required|string",
            "last_name" => "required|string",
            "phone" => "required|unique:users,phone|string",
            "document_number" => "required|numeric|digits:10",
            "password" => "required|string"
        ]);

        if ($validator->fails()) {
            $error = $validator->errors();
            $errors = (object) [
                "error" => (object) [
                    "code" => 422,
                    "massage" => "Validation error",
                    "errors" => $error
                ]
            ];

            return response()->json($errors, 422);
        }

        DB::table("users")->insert([
            "first_name" => $request->input("first_name"),
            "last_name" => $request->input("last_name"),
            "phone" => $request->input("phone"),
            "document_number" => $request->input("document_number"),
            "password" => Hash::make($request->input("password")),
            "api_token" => Str::random(80),
            "created_at" => Carbon::now()->toDateTimeString(),
            "updated_at" => Carbon::now()->toDateTimeString()
        ]);

        return response('',204);
    }

    public function login(Request $request) {
        $validator = Validator::make($request->all(), [
            "phone" => "required|string",
            "password" => "required|string"
        ]);

        if ($validator->fails()) {
            $error = $validator->errors();
            $errors = (object) [
                "error" => (object) [
                    "code" => 422,
                    "massage" => "Validation error",
                    "errors" => $error
                ]
            ];

            return response()->json($errors, 422);
        }

        $phone = $request->input("phone");
        $password = $request->input("password");

        if (Auth::attempt(["phone" => $phone, "password" => $password])) {
            $user = Auth::user();

            $response = (object) [
              "data" => (object) [
                  "token" => $user->api_token
              ]
            ];

            return response()->json($response, 200);
        } else {
            $errors = (object) [
                "error" => (object) [
                    "code" => 401,
                    "massage" => "Unauthorized",
                    "errors" => (object) [
                        "phone" => ["phone or password incorrect"]
                    ]
                ]
            ];

            return response()->json($errors, 401);
        }
    }

    public function airport(Request $request) {
        $airports = [];

        if ($request->has("query")) {
            $query = "%".$request->input("query")."%";
            $airports = DB::table("airports")
                ->Where("city", "LIKE", $query)
                ->OrWhere("name", "LIKE", $query)
                ->OrWhere("iata", "LIKE", $query)
                ->select("name", "iata")
                ->get();
        }

        $response = (object) [
          "data" => (object) [
              "items" => $airports
          ]
        ];

        return response()->json($response, 200);
    }

    public function flight(Request $request) {
        $validator = Validator::make($request->all(), [
            "from" => "required",
            "to" => "required",
            "date1" => "required|date",
            "date2" => "date",
            "passengers" => "required|numeric|between:1,8"
        ]);

        if ($validator->fails()) {
            $error = $validator->errors();
            $errors = (object) [
                "error" => (object) [
                    "code" => 422,
                    "massage" => "Validation error",
                    "errors" => $error
                ]
            ];

            return response()->json($errors, 422);
        }

        $airport_from = DB::table("airports")
            ->where("iata", $request->input("from"))
            ->first();

        $airport_to = DB::table("airports")
            ->where("iata", $request->input("to"))
            ->first();

        $flights_to = DB::table("flights")
            ->where([
                ["from_id", $airport_from->id],
                ["to_id", $airport_to->id]
            ])
            ->get();

        $flight_to = [];
        $flight_back = [];

        foreach ($flights_to as $val) {
            $flight_to[] = (object)[
                "flight_id" => $val->id,
                "flight_code" => $val->flight_code,
                "from" => (object)[
                    "city" => $airport_from->city,
                    "airport" => $airport_from->name,
                    "iata" => $airport_from->iata,
                    "date" => $request->input("date1"),
                    "time" => $val->time_from,
                ],
                "to" => (object)[
                    "city" => $airport_to->city,
                    "airport" => $airport_to->name,
                    "iata" => $airport_to->iata,
                    "date" => $request->input("date1"),
                    "time" => $val->time_to,
                ],
                "cost" => $val->cost,
                "availability" => "null"
            ];
        }

        if ($request->has("date2")) {
            $flights_back = DB::table("flights")
                ->where([
                    ["from_id", $airport_to->id],
                    ["to_id", $airport_from->id]
                ])
                ->get();

            foreach ($flights_back as $val) {
                $flight_back[] = (object)[
                    "flight_id" => $val->id,
                    "flight_code" => $val->flight_code,
                    "from" => (object)[
                        "city" => $airport_to->city,
                        "airport" => $airport_to->name,
                        "iata" => $airport_to->iata,
                        "date" => $request->input("date2"),
                        "time" => $val->time_from
                    ],
                    "to" => (object)[
                        "city" => $airport_from->city,
                        "airport" => $airport_from->name,
                        "iata" => $airport_from->iata,
                        "date" => $request->input("date2"),
                        "time" => $val->time_to
                    ],
                    "cost" => $val->cost,
                    "availability" => "null"
                ];
            }

            $response = (object) [
              "data" => (object) [
                  "flights_to" => $flight_to,
                  "flights_back" => $flight_back
              ]
            ];

            return response()->json($response, 200);
        }
    }

    public function booking(Request $request) {
        $data = (object) $request->all();
        $arr = array();
        $errors = array();

        for($i = 0; $i < count($data->passengers); $i++) {
            $arr[$i] = [
                "first_name" => $data->passengers[$i]["first_name"],
                "last_name" => $data->passengers[$i]["last_name"],
                "birth_date" => $data->passengers[$i]["birth_date"],
                "document_number" => $data->passengers[$i]["document_number"],
            ];

            $validator = Validator::make($arr[$i], [
                "first_name" => "required|string",
                "last_name" => "required|string",
                "birth_date" => "required|date",
                "document_number" => "required|digits:10",
            ]);

            $errors[$i] = $validator->errors();
        }

        if (count($errors) != 0) {
           $response = (object) [
             "error" => (object) [
                 "code" => 422,
                 "message" => "Validation error",
                 "errors" => $errors
             ]
           ];

           return response()->json($response, 422);
        }

        $ts = Carbon::now()->toDateTimeString();
        $code = Random::generate(5, 'A-Z');

        $id = DB::table("bookings")
            ->insertGetId([
                "flight_from" => $data->flight_from["id"],
                "flight_back" => $data->flight_back["id"],
                "date_from" => $data->flight_from["date"],
                "date_back" => $data->flight_back["date"],
                "code" => $code,
                "created_at" => $ts,
                "updated_at" => $ts
            ]);

        if (Auth::check()) {
            $user = Auth::user();
            DB::table("passengers")
                ->insert([
                    "booking_id" => $id,
                    "first_name" => $user->first_name,
                    "last_name" => $user->last_name,
                    "birth_date" => "null",
                    "document_number" => $user->document_number,
                    "created_at" => $ts,
                    "updated_at" => $ts
                ]);
        }

        for($i = 0; $i < count($data->passengers); $i++) {
            DB::table("passengers")
                ->insert([
                    "booking_id" => $id,
                    "first_name" => $data->passengers[$i]["first_name"],
                    "last_name" => $data->passengers[$i]["last_name"],
                    "birth_date" => $data->passengers[$i]["birth_date"],
                    "document_number" => $data->passengers[$i]["document_number"],
                    "created_at" => $ts,
                    "updated_at" => $ts
                ]);
        }

        $response = (object) [
          "data" => (object) [
              "code" => $code
          ]
        ];

        return response()->json($response, 201);
    }

    public function booking_show(Request $request) {
        $code = $request->route("code");

        $booking = DB::table("bookings")
            ->where("code", $code)
            ->first();

        $flights_from = DB::table("flights")
            ->where("id", $booking->flight_from)
            ->first();

        $flights_back = DB::table("flights")
            ->where("id", $booking->flight_back)
            ->first();

        $airport_from = DB::table("airports")
            ->where("id", $flights_from->from_id)
            ->first();

        $airport_to = DB::table("airports")
            ->where("id", $flights_from->to_id)
            ->first();

        $passengers = DB::table("passengers")
            ->where("booking_id", $booking->id)
            ->select("id", "first_name", "last_name", "birth_date", "document_number", "place_from", "place_back")
            ->get();

        $allcost = ($flights_from->cost + $flights_back->cost) * count($passengers);

        $response = (object)[
            "data" => (object)[
                "code" => $code,
                "cost" => $allcost,
                "flights" => [
                    "0" => (object)[
                        "flight_id" => $flights_from->id,
                        "flight_code" => $flights_from->flight_code,
                        "flight_id" => $flights_from->id,
                        "flight_code" => $flights_from->flight_code,
                        "from" => (object)[
                            "city" => $airport_from->city,
                            "airport" => $airport_from->name,
                            "iata" => $airport_from->iata,
                            "date" => $booking->date_from,
                            "time" => $flights_from->time_from
                        ],
                        "to" => (object)[
                            "city" => $airport_to->city,
                            "airport" => $airport_to->name,
                            "iata" => $airport_to->iata,
                            "date" => $booking->date_from,
                            "time" => $flights_from->time_to
                        ],
                        "cost" => $flights_from->cost,
                        "availability" => "null"
                    ],
                    "1" => (object)[
                        "flight_id" => $flights_back->id,
                        "flight_code" => $flights_back->flight_code,
                        "flight_id" => $flights_back->id,
                        "flight_code" => $flights_back->flight_code,
                        "from" => (object)[
                            "city" => $airport_to->city,
                            "airport" => $airport_to->name,
                            "iata" => $airport_to->iata,
                            "date" => $booking->date_back,
                            "time" => $flights_back->time_from
                        ],
                        "to" => (object)[
                            "city" => $airport_from->city,
                            "airport" => $airport_from->name,
                            "iata" => $airport_from->iata,
                            "date" => $booking->date_back,
                            "time" => $flights_back->time_to
                        ],
                        "cost" => $flights_back->cost,
                        "availability" => "null"
                    ],
                ],
                "passengers" => $passengers
            ]
        ];

        return response()->json($response, 200);
    }

    public function seat(Request $request) {
        if ($request->isMethod("GET")) {
            $code = $request->code;
            $booking = DB::table("bookings")
                ->where("code", $code)
                ->first();

            $place_from = DB::table("passengers")
                ->where("booking_id", $booking->id)
                ->whereNotNull("place_from")
                ->select("id", "place_from")
                ->get();

            $place_back = DB::table("passengers")
                ->where("booking_id", $booking->id)
                ->whereNotNull("place_back")
                ->select("id", "place_back")
                ->get();

            $occ_from = array();
            $occ_back = array();

            for($i = 0; $i < count($place_from); $i++) {
                $occ_from[] = (object)[
                    "passenger_id" => $place_from[$i]->id,
                    "place" => $place_from[$i]->place_from
                ];

                $occ_back[] = (object)[
                    "passenger_id" => $place_back[$i]->id,
                    "place" => $place_back[$i]->place_back
                ];
            }

            $response = (object)[
                "data" => (object)[
                    "occupied_from" => $occ_from,
                    "occupied_back" => $occ_back
                ]
            ];

            return response()->json($response, 200);
        } else {
            $validator = Validator::make($request->all(), [
                "passenger" => "required",
                "seat" => "required|between:2,3",
                "type" => "required"
            ]);

            if($validator->fails()) {
                $error = $validator->errors();

                $errors = (object) [
                    "error" => (object)[
                        "code" => 422,
                        "message" => "Validation error",
                        "errors" => $error
                    ]
                ];

                return response()->json($errors, 422);
            }

            $id = $request->input("passenger");
            $seat = $request->input("seat");
            $type = $request->input("type");
            $code = $request->route("code");

            $booking = DB::table("bookings")
                ->where("code", $code)
                ->first();

            $passenger = DB::table("passengers")
                ->where("id", $id)
                ->first();

            if($booking->id != $passenger->booking_id) {
                // Составление обьекта отрицательного ответа
                $errors = (object)[
                    "error" => (object)[
                        "code" => 403,
                        "message" => "Passenger does nt apply to booking"
                    ]
                ];

                return response()->json($errors, 403);
            }

            $col = $type == "from" ? "place_from" :  "place_back";
            $pass = DB::table("passengers")
                ->where($col, $seat)
                ->get();

            if($pass == []) {
                DB::table("passengers")
                    ->where("id", $id)
                    ->update([
                        $col => $seat
                    ]);
            }
            else {
                $errors = (object) [
                    "error" => (object)[
                        "code" => 422,
                        "message" => "Seat is occupied"
                    ]
                ];

                return response()->json($errors, 422);
            }

            $psg = DB::table("passengers")
                ->where("id", $id)
                ->select("id", "first_name", "last_name", "birth_date", "document_number", "place_from", "place_back")
                ->first();

            $response = (object) [
              "data" => $psg
            ];

            return response()->json($response, 200);
        }
    }

    public function user(Request $request) {
        $user = DB::table('users')
            ->where('api_token', $request->bearerToken())
            ->first();

        if($user) {
            $response = (object) [
                "first_name" => $user->first_name,
                "last_name" => $user->last_name,
                "phone" => $user->phone,
                "document_number" => $user->document_number,
            ];

            return response()->json($response, 200);
        } else {
            $errors = (object) [
                "error" => (object)[
                    "code" => 401,
                    "message" => "Unauthorized"
                ]
            ];

            return response()->json($errors, 401);
        }
    }

    public function user_booking(Request $request) {
        $user = DB::table('users')
            ->where('api_token', $request->bearerToken())
            ->first();

        if($user) {
            $pass = DB::table("passengers")
                ->where("document_number", $user->document_number)
                ->get();

            $items = array();

            for($i = 0; $i < count($pass); $i++) {
                $booking = DB::table("bookings")
                    ->where("id", $pass[$i]->booking_id)
                    ->first();

                $flights_from = DB::table("flights")
                    ->where("id", $booking->flight_from)
                    ->first();

                $flights_back = DB::table("flights")
                    ->where("id", $booking->flight_back)
                    ->first();

                $airport_from = DB::table("airports")
                    ->where("id", $flights_from->from_id)
                    ->first();

                $airport_to = DB::table("airports")
                    ->where("id", $flights_from->to_id)
                    ->first();

                $passengers = DB::table("passengers")
                    ->where("booking_id", $booking->id)
                    ->select("id", "first_name", "last_name", "birth_date", "document_number", "place_from", "place_back")
                    ->get();

                $allcost = ($flights_from->cost + $flights_back->cost) * count($passengers);

                $items[] = (object)[
                    "code" => $booking->code,
                    "cost" => $allcost,
                    "flights" => [
                        "0" => (object)[
                            "flight_id" => $flights_from->id,
                            "flight_code" => $flights_from->flight_code,
                            "flight_id" => $flights_from->id,
                            "flight_code" => $flights_from->flight_code,
                            "from" => (object)[
                                "city" => $airport_from->city,
                                "airport" => $airport_from->name,
                                "iata" => $airport_from->iata,
                                "date" => $booking->date_from,
                                "time" => $flights_from->time_from
                            ],
                            "to" => (object)[
                                "city" => $airport_to->city,
                                "airport" => $airport_to->name,
                                "iata" => $airport_to->iata,
                                "date" => $booking->date_from,
                                "time" => $flights_from->time_to
                            ],
                            "cost" => $flights_from->cost,
                            "availability" => "null"
                        ],
                        "1" => (object)[
                            "flight_id" => $flights_back->id,
                            "flight_code" => $flights_back->flight_code,
                            "flight_id" => $flights_back->id,
                            "flight_code" => $flights_back->flight_code,
                            "from" => (object)[
                                "city" => $airport_to->city,
                                "airport" => $airport_to->name,
                                "iata" => $airport_to->iata,
                                "date" => $booking->date_back,
                                "time" => $flights_back->time_from
                            ],
                            "to" => (object)[
                                "city" => $airport_from->city,
                                "airport" => $airport_from->name,
                                "iata" => $airport_from->iata,
                                "date" => $booking->date_back,
                                "time" => $flights_back->time_to
                            ],
                            "cost" => $flights_back->cost,
                            "availability" => "null"
                        ],
                    ],
                    "passengers" => $passengers
                ];
            }

            $response = (object)[
                "data" => (object)[
                    "items" => $items
                ]
            ];

            return response()->json($response, 200);
        } else {
            $errors = (object) [
                "error" => (object)[
                    "code" => 401,
                    "message" => "Unauthorized"
                ]
            ];

            return response()->json($errors, 401);
        }
    }
}
