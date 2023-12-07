<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\GradeResource;

class GradeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request['perPage'];
            $data = Grade::orderByDesc('id')->where('title', 'Like', '%' . $request['search'] . '%')->paginate($perPage);
            $pages_count = ceil($data->total() / $perPage);
            $labels = [];
            for ($i = 1; $i <= $pages_count; $i++) {
                (array_push($labels, $i));
            }
            return response([
                "data" => GradeResource::collection($data),
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
            $data = Grade::all()->sortByDesc('id')->take(3);
            return response(GradeResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function show(Grade $grade)
    {
        try {
            return response(new GradeResource($grade), 200);
        } catch (\Exception $exception) {

            return response($exception);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:Grades,title',
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
            $data = Grade::create($request->except('image'));
            if ($request['image']) {
                $name = 'Grade_' . $data['id'] . '_' . uniqid() . '.jpg';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/Grades/');
                $data->update(['image' => '/' . $image_path]);
                (new ImageController)->resizeImage('images/Grades/',$name);

            }

            return response(new GradeResource($data), 201);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function update(Request $request, Grade $grade)
    {
//        return $request;

        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:Grades,title,' . $grade['id'],
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
            $grade->update($request->except('image'));

            if ($request['image']) {
                $name = 'Grade_' . $grade['id'] . '_' . uniqid() . '.jpg';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/Grades/');

                if ($grade['image']){
                    $file_to_delete = ltrim($grade['image'], $grade['image'][0]); //remove '/' from file name start
                    $file_to_delete_thumb = ltrim(str_replace('.png','_thumb.png',$file_to_delete));
                    if (file_exists($file_to_delete)){  unlink($file_to_delete);}
                    if (file_exists($file_to_delete_thumb)){  unlink($file_to_delete_thumb);}
                }

                $grade->update(['image' => '/' . $image_path]);
                (new ImageController)->resizeImage('images/Grades/',$name);


            }

            return response(new GradeResource($grade), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

//    public function destroy(Grade $grade)
    public function destroy($id)
    {
        $data = Grade::where('id', $id)->first();
        try {
            if ($data['image']){
                $file_to_delete = ltrim($data['image'], $data['image'][0]); //remove '/' from file name start
                $file_to_delete_thumb = ltrim(str_replace('.png','_thumb.png',$file_to_delete));
                if (file_exists($file_to_delete)){  unlink($file_to_delete);}
                if (file_exists($file_to_delete_thumb)){  unlink($file_to_delete_thumb);}
            }
            $data->delete();
            return response('Grade deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function activeToggle(Grade $grade)
    {

        try {
            $grade->update(['active' => !$grade['active']]);
            return response(new GradeResource($grade), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
    public function byCat($id)
    {
        try {
            $data = Grade::orderByDesc('id')->where('Grade_category_id', $id)->where('active',1)->get();

            return response([
                "data"=>GradeResource::collection($data),

            ], 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }
}
