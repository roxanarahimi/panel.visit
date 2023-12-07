<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\VisitorResource;

class VisitorController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request['perPage'];
            $data = Visitor::orderByDesc('id')->where('title', 'Like', '%' . $request['search'] . '%')->paginate($perPage);
            $pages_count = ceil($data->total() / $perPage);
            $labels = [];
            for ($i = 1; $i <= $pages_count; $i++) {
                (array_push($labels, $i));
            }
            return response([
                "data" => VisitorResource::collection($data),
                "pages" => $pages_count,
                "total" => $data->total(),
                "labels" => $labels,
                "title" => 'برند ها',
                "tooltip_new" => 'ثبت برند جدید',

            ], 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function latest()
    {
        try {
            $data = Visitor::all()->sortByDesc('id')->take(3);
            return response(VisitorResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function show(Visitor $visitor)
    {
        try {
            return response(new VisitorResource($visitor), 200);
        } catch (\Exception $exception) {

            return response($exception);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:Visitors,title',
            ],
            [
                'title.required' => 'لطفا عنوان را وارد کنید',
                'title.unique' => 'این عنوان قبلا ثبت شده است',
            ]
        );
        if ($validator->fails()) {
            return response()->json($validator->messages(), 422);
        }
        try {
            $data = Visitor::create($request->except('image'));
            if ($request['image']) {
                $name = 'Visitor_' . $data['id'] . '_' . uniqid() . '.jpg';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/Visitors/');
                $data->update(['image' => '/' . $image_path]);
                (new ImageController)->resizeImage('images/Visitors/',$name);

            }

            return response(new VisitorResource($data), 201);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function update(Request $request, Visitor $visitor)
    {
//        return $request;

        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:Visitors,title,' . $visitor['id'],
            ],
            [
                'title.required' => 'لطفا عنوان را وارد کنید',
                'title.unique' => 'این عنوان قبلا ثبت شده است',
            ]
        );
        if ($validator->fails()) {
            return response()->json($validator->messages(), 422);
        }
        try {
            $visitor->update($request->except('image'));

            if ($request['image']) {
                $name = 'Visitor_' . $visitor['id'] . '_' . uniqid() . '.jpg';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/Visitors/');

                if ($visitor['image']){
                    $file_to_delete = ltrim($visitor['image'], $visitor['image'][0]); //remove '/' from file name start
                    $file_to_delete_thumb = ltrim(str_replace('.png','_thumb.png',$file_to_delete));
                    if (file_exists($file_to_delete)){  unlink($file_to_delete);}
                    if (file_exists($file_to_delete_thumb)){  unlink($file_to_delete_thumb);}
                }

                $visitor->update(['image' => '/' . $image_path]);
                (new ImageController)->resizeImage('images/Visitors/',$name);


            }

            return response(new VisitorResource($visitor), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

//    public function destroy(Visitor $visitor)
    public function destroy($id)
    {
        $data = Visitor::where('id', $id)->first();
        try {
            if ($data['image']){
                $file_to_delete = ltrim($data['image'], $data['image'][0]); //remove '/' from file name start
                $file_to_delete_thumb = ltrim(str_replace('.png','_thumb.png',$file_to_delete));
                if (file_exists($file_to_delete)){  unlink($file_to_delete);}
                if (file_exists($file_to_delete_thumb)){  unlink($file_to_delete_thumb);}
            }
            $data->delete();
            return response('Visitor deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function activeToggle(Visitor $visitor)
    {

        try {
            $visitor->update(['active' => !$visitor['active']]);
            return response(new VisitorResource($visitor), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
    public function byCat($id)
    {
        try {
            $data = Visitor::orderByDesc('id')->where('Visitor_category_id', $id)->where('active',1)->get();

            return response([
                "data"=>VisitorResource::collection($data),

            ], 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }
}
