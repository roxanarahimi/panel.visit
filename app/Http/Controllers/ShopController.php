<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\ShopResource;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request['perPage'];
            $data = Shop::orderByDesc('id')->where('title', 'Like', '%' . $request['search'] . '%')->paginate($perPage);
            $pages_count = ceil($data->total() / $perPage);
            $labels = [];
            for ($i = 1; $i <= $pages_count; $i++) {
                (array_push($labels, $i));
            }
            return response([
                "data" => ShopResource::collection($data),
                "pages" => $pages_count,
                "total" => $data->total(),
                "labels" => $labels,
                "title" => 'فروشگاه ها',
                "tooltip_new" => 'ثبت فروشگاه جدید',

            ], 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function latest()
    {
        try {
            $data = Shop::all()->sortByDesc('id')->take(3);
            return response(ShopResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function show(Shop $shop)
    {
        try {
            return response(new ShopResource($shop), 200);
        } catch (\Exception $exception) {

            return response($exception);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:Shops,title',
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
            $data = Shop::create($request->except('image'));
            if ($request['image']) {
                $name = 'Shop_' . $data['id'] . '_' . uniqid() . '.jpg';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/Shops/');
                $data->update(['image' => '/' . $image_path]);
                (new ImageController)->resizeImage('images/Shops/',$name);

            }

            return response(new ShopResource($data), 201);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function update(Request $request, Shop $shop)
    {
//        return $request;

        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:Shops,title,' . $shop['id'],
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
            $shop->update($request->except('image'));

            if ($request['image']) {
                $name = 'Shop_' . $shop['id'] . '_' . uniqid() . '.jpg';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/Shops/');

                if ($shop['image']){
                    $file_to_delete = ltrim($shop['image'], $shop['image'][0]); //remove '/' from file name start
                    $file_to_delete_thumb = ltrim(str_replace('.png','_thumb.png',$file_to_delete));
                    if (file_exists($file_to_delete)){  unlink($file_to_delete);}
                    if (file_exists($file_to_delete_thumb)){  unlink($file_to_delete_thumb);}
                }

                $shop->update(['image' => '/' . $image_path]);
                (new ImageController)->resizeImage('images/Shops/',$name);


            }

            return response(new ShopResource($shop), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

//    public function destroy(Shop $shop)
    public function destroy($id)
    {
        $data = Shop::where('id', $id)->first();
        try {
            if ($data['image']){
                $file_to_delete = ltrim($data['image'], $data['image'][0]); //remove '/' from file name start
                $file_to_delete_thumb = ltrim(str_replace('.png','_thumb.png',$file_to_delete));
                if (file_exists($file_to_delete)){  unlink($file_to_delete);}
                if (file_exists($file_to_delete_thumb)){  unlink($file_to_delete_thumb);}
            }
            $data->delete();
            return response('Shop deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function activeToggle(Shop $shop)
    {

        try {
            $shop->update(['active' => !$shop['active']]);
            return response(new ShopResource($shop), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
    public function byCat($id)
    {
        try {
            $data = Shop::orderByDesc('id')->where('Shop_category_id', $id)->where('active',1)->get();

            return response([
                "data"=>ShopResource::collection($data),

            ], 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }
}
