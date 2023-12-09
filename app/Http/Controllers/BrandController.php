<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BrandCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\BrandResource;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request['perPage'];
            $data = Brand::orderByDesc('id')->where('title', 'Like', '%' . $request['search'] . '%')->paginate($perPage);
            $pages_count = ceil($data->total() / $perPage);
            $labels = [];
            for ($i = 1; $i <= $pages_count; $i++) {
                (array_push($labels, $i));
            }
            return response([
                "data" => BrandResource::collection($data),
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
            $data = Brand::all()->sortByDesc('id')->take(3);
            return response(BrandResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function show(Brand $brand)
    {
        try {
            return response(new BrandResource($brand), 200);
        } catch (\Exception $exception) {

            return response($exception);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:Brands,title',
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
            $data = Brand::create($request->except('image'));

            foreach($request['categories'] as $item){
                BrandCategory::create([
                    "brand_id" => $data['id'],
                    "product_category_id" => $item['id']
                ]);
            }

            return response(new BrandResource($data), 201);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function update(Request $request, Brand $brand)
    {
//        return $request;

        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:Brands,title,' . $brand['id'],
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
            $brand->update($request->except('image'));

            $old = BrandCategory::where('brand_id', $brand['id'])->get();
            foreach($old as $item){ $item->delete();}

            foreach($request['categories'] as $item){
                BrandCategory::create([
                    "brand_id" => $brand['id'],
                    "product_category_id" => $item['id']
                ]);
            }

            return response(new BrandResource($brand), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

//    public function destroy(Brand $brand)
    public function destroy($id)
    {
        $data = Brand::where('id', $id)->first();
        try {

            $old = BrandCategory::where('brand_id', $id)->get();
            if($old) {
                foreach ($old as $item) {
                    $item->delete();
                }
            }

            $data->delete();
            return response('Brand deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function activeToggle(Brand $brand)
    {

        try {
            $brand->update(['active' => !$brand['active']]);
            return response(new BrandResource($brand), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
    public function byCat($id)
    {
        try {
            $data = Brand::orderByDesc('id')->where('Brand_category_id', $id)->where('active',1)->get();

            return response([
                "data"=>BrandResource::collection($data),

            ], 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }
}
