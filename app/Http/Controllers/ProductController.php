<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Article;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\RelatedProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mockery\Exception;

//use Redis;
//use Illuminate\Support\Facades\Redis;


class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request['perPage'];
            $data = Product::orderBy('index')->orderByDesc('id')->where('title', 'Like', '%' . $request['search'] . '%')->paginate($perPage);
            $pages_count = ceil($data->total()/$perPage);
            $labels = [];
            for ($i=1; $i <= $pages_count; $i++){
                (array_push($labels,$i));
            }
            return response([
                "data"=>ProductResource::collection($data),
                "pages"=>$pages_count,
                "total"=> $data->total(),
                "labels"=> $labels,
                "title"=> 'محصولات',
                "tooltip_new"=> 'ثبت محصول جدید',
            ], 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function latest()
    {

        try {
            $data = Product::all()->sortByDesc('id')->take(4);
            return response(ProductResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function indexSite(Request $request)
    {
        // dd($request->all());
        try {
            $data = Product::orderBy('index')->orderByDesc('id')->whereHas('activeCategory')->with('category')->where('active', 1);
            if ($request['cat'] != '') {
                $data = $data->where('product_category_id', $request['cat']);
            }
            if ($request['stock'] == 'true') {
                $data = $data->where('stock', '>', 0);
            }elseif ($request['stock'== 'limited']){
                $data = $data->where('stock', '>', 0)->where('stock','<',5);
            }
//            if ($request['cat_ids'] != '') {
//                $ids = explode(',', $request['cat_ids']);
//                $data = $data->whereIn('product_category_id', $ids);
//
//            }
            if ($request['off'] == 'true') {
                $data = $data->where('off', '>', 0)->where('stock', '>', 0);

            }
            if ($request['search'] != '') {
                $data = $data->where('title', 'Like', '%' . $request['search'] . '%');

            }
            if ($request['sort'] != '') {
                switch ($request['sort']) {

                    case ('sale'):
                    {
                        $data = $data->orderByDesc('sale');
                        break;
                    }
                    case ('score'):
                    {
                        $data = $data->orderByDesc('score');
                        break;
                    }
                    case ('cheap'):
                    {
                        $data = $data->orderBy('price');
                        break;
                    }
                    case ('expensive'):
                    {
                        $data = $data->orderByDesc('price');
                        break;
                    }
                    case ('view'):
                    {
                        $data = $data->orderByDesc('view');
                        break;
                    }
                    default:
                    {
                        $data = $data->orderByDesc('id');
                        break;
                    }
                }
            }
            if ($request['sale'] == 'true') {
                $data = $data->orderByDesc('sale');

            }
            if ($request['limit'] != '') {
                $data = $data->skip(0)->take($request['limit']);
            }
            $data = $data->get();

            return response(ProductResource::collection($data), 200);
//            return response(new ProductResource($data), 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function stockSite()
    {
        try {
//
            $data = Product::whereHas('activeCategory')->with('category')->where('active', 1)->where('stock', '>', 0)->latest()->get();

            return response(ProductResource::collection($data), 200);
//            return response(new ProductResource($data), 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function latestSite()
    {
        try {
            $data = Product::whereHas('activeCategory')->with('category')->where('active', 1)->take(4)->latest()->get();
            return response(ProductResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function show(Product $product)
    {
        try {

            $sizes = ProductSize::where('product_id', $product['id'])->where('stock', '>', 0)->get(['color_name', 'color_code']);
            $sizes = json_decode($sizes);
//            return $sizes;
            $colors = [];
            foreach ($sizes as $item) {
                array_push($colors, json_encode(['color_name' => $item->color_name, 'color_code' => $item->color_code]));
            }
            return response(['product' => new ProductResource($product), 'colors' => array_unique($colors)], 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function getSizes($id, $color)
    {
        try {

            $data = ProductSize::where('product_id', $id)->where('color_name', $color)->where('stock', '>', 0)->get();
            return response($data, 200);

        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function saveImages($requestImages, $productId)
    {
        try {
            $images = '';
            for ($i = 0; $i < count($requestImages); $i++) {
                if ($requestImages[$i][1]) {
                    $name = 'product_' . $productId . '_' . uniqid() . '.png';
                    $image_path = (new ImageController)->uploadImage($requestImages[$i][1], $name, 'images/');
//                    (new ImageController)->resizeImage('images/', $name);
                    $images = $images . '/' . $image_path . ',';
                } else if ($requestImages[$i][0]) {
                    $images = $images . $requestImages[$i][0] . ',';
                }
            }

            return $images;
        }catch (Exception $exception){
            return $exception;
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:products,title',
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
            $product = Product::create($request->except('image','related_products'));
            if ($request['image']) {
                $name = 'product_' . $product['id'] . '_' . uniqid() . '.png';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/products/');
                $product->update(['image' => '/' . $image_path]);

                (new ImageController)->resizeImage('images/products/',$name);
            }
            if ($request['related_products']){
                foreach ($request['related_products'] as $item){
                    RelatedProduct::create([
                        'product_id' => $product['id'],
                        'related_product_id' => $item,
                    ]);
                }
            }
            return response(new ProductResource($product), 201);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function update(Request $request, Product $product)
    {
        $validator = Validator::make($request->all('title'),
            [
                'title' => 'required|unique:products,title,' . $product['id'],
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
            $product->update($request->except('image','related_products'));
            if ($request['image']) {
                $name = 'product_' . $product['id'] . '_' . uniqid() . '.png';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/products/');

                if ($product['image']){
                    $file_to_delete = ltrim($product['image'], $product['image'][0]); //remove '/' from file name start
                    $file_to_delete_thumb = ltrim(str_replace('.png','_thumb.png',$file_to_delete));
                    if (file_exists($file_to_delete)){  unlink($file_to_delete);}
                    if (file_exists($file_to_delete_thumb)){  unlink($file_to_delete_thumb);}
                }

                $product->update(['image' => '/' . $image_path]);
                (new ImageController)->resizeImage('images/products/',$name);


            }

            $relatedZ = RelatedProduct::where('product_id', $request['id'])->get();
            foreach ($relatedZ as $item){ $item->delete();}

            if ($request['related_products']){
                foreach ($request['related_products'] as $item){
                    RelatedProduct::create([
                        'product_id' => $product['id'],
                        'related_product_id' => $item,
                    ]);
                }
            }

            return response(new ProductResource($product), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
    public function sort(Request $request, Product $product)
    {
//        $validator = Validator::make($request->all('title'),
//            [
//                'title' => 'required|unique:products,title,' . $product['id'],
//            ],
//            [
//                'title.required' => 'لطفا عنوان را وارد کنید',
//                'title.unique' => 'این عنوان قبلا ثبت شده است',
//            ]
//        );
//
//        if ($validator->fails()) {
//            return response()->json($validator->messages(), 422);
//        }
        try {
            $product->update($request->all('index'));
            return response(new ProductResource($product), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function destroy(Product $product)
    {

        try {
            $relatedZ = RelatedProduct::where('product_id', $product['id'])->get();
            foreach ($relatedZ as $item){ $item->delete();}
            if ($product['image']){
                $file_to_delete = ltrim($product['image'], $product['image'][0]); //remove '/' from file name start
                $file_to_delete_thumb = ltrim(str_replace('.png','_thumb.png',$file_to_delete));
                if (file_exists($file_to_delete)){  unlink($file_to_delete);}
                if (file_exists($file_to_delete_thumb)){  unlink($file_to_delete_thumb);}
            }
            $product->delete();
            return response('product deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function activeToggle(Product $product)
    {
        try {
            $product->update(['active' => !$product['active']]);
            return response(new ProductResource($product), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
    public function updateOrder(Request $request, Product $product )
    {
        try {

            $product->update(['images' => substr($request['images'], 0, -1)]);
            return response(new ProductResource($product), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
    public function byCatPanel($id)
    {
        try {
            $data = Product::orderBy('title')->where('product_category_id', $id)->where('active',1)->get();


            return response(["data"=>ProductResource::collection($data)], 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }
    public function byCat($id)
    {
        try {
            $data = Product::orderBy('id')->where('product_category_id', $id)->where('active',1)->get();

            foreach ($data as $item){
                $thumb2 = $item->image ? str_replace('.png','_thumb.png', $item->image) : '';
                $item->thumb = $thumb2;
            }
            return response([
                "data"=>$data,

            ], 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function fix(Request $request){
        $json = array(
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "مرغ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "سبزیجات",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "جو و قارچ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "جو و گوجه فرنگی",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "مرغ با ورمیشل",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "جو",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "قارچ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "پیاز فرانسوی",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "جو و قارچ با خامه الیت پلاس",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "مرغ و سبزیجات الیت پلاس",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "سبزیجات و ورمیشل الیت پلاس",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "لیوانی قارچ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "لیوانی سبزیجات",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "لیوانی مرغ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "مرغ و ذرت",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "پودر حلیم",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "پودر سوخاری",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "کتلت و همبرگر",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "تیلیت",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "name" => "سس بشامل",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "name" => "جو",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "name" => "مرغ",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "name" => "مرغ و ورمیشل",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "name" => "قارچ",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "name" => "سبزیجات",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "name" => "جو و قارچ",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "name" => "جو و گوجه فرنگی",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => false, "name" => "پودر سوخاری",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "name" => "مرغ",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "name" => "سبزی",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "name" => "جو و قارچ",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "name" => "مرغ و ورمیشل",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "name" => "جو",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "name" => "قارچ",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "گوشت",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "مرغ",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "سبزیجات",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "گوجه تند",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "قارچ و پنیر",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "قارچ",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "زعفران"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "کاری"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "ماسالا"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "باربیکیو"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "کودک سبزی"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "کودک گوشت"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "کودک مرغ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "لیوانی سبزیجات"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "لیوانی گوشت"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "لیوانی مرغ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "پک 5 عددی گوشت"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "پک 5 عددی مرغ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "پک 5 عددی سبزی"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "پک 5 عددی گوجه تند"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "پک 5 عددی قارچ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "پک 5 عددی قارچ و پنیر"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "name" => "پک 5 عددی باربیکیو"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "کودک گوشت"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "کودک مرغ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "کودک سبزیجات"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "کودک قارچ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "کودک قارچ و پنیر"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "کودک گوجه تند"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "کودک کاری"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "پک 5 عددی گوشت"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "پک 5 عددی مرغ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "پک 5 عددی سبزی"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "پک 5 عددی قارچ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "پک 5 عددی قارچ و پنیر"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "name" => "پک 5 عددی گوجه"),
            array("type" => "نودل", "brand" => "نودیلند", "is_main" => true, "name" => "گوشت"),
            array("type" => "نودل", "brand" => "نودیلند", "is_main" => true, "name" => "مرغ"),
            array("type" => "نودل", "brand" => "نودیلند", "is_main" => true, "name" => "سبزی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "مرغ 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "بره 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "گوساله 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "کاری 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "برنج 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "لیمو عمانی 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "سیر 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "سبزیجات 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "پیاز 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "قارچ 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "جوجه 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "گوجه فرنگی 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "زعفران 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "مرغ 12 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "بره 12 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "گوساله 12 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "پک زعفران 3 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "پک مرغ 48 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "پک بره 48 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "name" => "پک گوساله 48 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "name" => "گوساله 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "name" => "بره 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "name" => "مرغ 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "name" => "زعفران 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "name" => "پک مرغ 48 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "name" => "پک بره 48 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "name" => "پک گوساله 48 عددی"),
            array("type" => "عصاره", "brand" => "نودیلند", "is_main" => true, "name" => "گوساله"),
            array("type" => "عصاره", "brand" => "نودیلند", "is_main" => true, "name" => "مرغ"),
            array("type" => "عصاره", "brand" => "نودیلند", "is_main" => true, "name" => "بره"),
            array("type" => "پرک", "brand" => "الیت", "is_main" => true, "name" => "جو پرک"),
            array("type" => "پرک", "brand" => "الیت", "is_main" => true, "name" => "گندم پرک"),
            array("type" => "آش", "brand" => "الیت", "is_main" => true, "name" => "جو"),
            array("type" => "آش", "brand" => "الیت", "is_main" => true, "name" => "رشته"),
            array("type" => "آش", "brand" => "الیت", "is_main" => true, "name" => "سبزی"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "چاشنی خوراک با زعفران"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "چاشنی خورشت با زعفران"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "چاشنی خوراک با کاری"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "چاشنی خورشت قیمه"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "چاشنی خورشت قورمه سبزی"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "فلفل سیاه"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "فلفل قرمز"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "فلفل سفید"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "سماق"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "سیر"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "زردچوبه"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "پودر سالاد"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "پودر کباب"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "پودر ماست"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "پودر لیمو و فلفل"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "پودر دارچین"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "پودر کاری"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "آویشن"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "پودر زنجبیل"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "پودر پاپریکا"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "name" => "پودر پیاز"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => true, "name" => "گوجه و ریحان"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => true, "name" => "قارچ و پنیر"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => false, "name" => "ریحان با خامه"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => false, "name" => "سس بلونیز"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => false, "name" => "کاری با خامه"),
            array("type" => "پیاز داغ", "brand" => "الیت", "is_main" => true, "name" => "پیاز داغ"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کریمر متوسط"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کریمر بزرگ"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کونیگ 90"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کونیگ 170"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "باکسی شکلاتی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "باکسی آیرش کریم"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "باکسی بدون قند"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "باکسی فندقی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "باکسی وانیلی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "باکسی کلاسیک"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "باکسی هات چاکلت"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کیسه 40 عددی کلاسیک"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کیسه 40 عددی فندقی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کیسه 40 عددی وانیلی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کیسه 40 عددی هات چاکلت"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کیسه 40 عددی بدون قند"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کیسه 40 عددی آیرش کریم"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کیسه 40 عددی شکلاتی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کلاسیک 40 عددی عقابی آروما"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "name" => "کاپوچینو"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "کاکائویی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "عسلی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "کاکائو ذرت"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "بالشتی شکلاتی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "حلقه ای شکلاتی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "کورن فلکس مورنینگ لایت"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "کورن فلکس اسپشیال سی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "دو رنگ 100 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "فندقی 100 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "دو رنگ 200 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "فندقی 200 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "دو رنگ 330 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "name" => "فندقی 330 گرمی"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "name" => "مینی کانتی شیری"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "name" => "مینی کانتی دارک"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "name" => "کانتی شیری"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "name" => "کانتی دارک"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "name" => "کانتی نعنایی"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "name" => "کانتی پرتغالی"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "name" => "پک کانتی شیری"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "name" => "پک کانتی دارک"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => false, "name" => "پک کانتی با طعم نعنا"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => false, "name" => "پک کانتی با طعم پرتغالی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "شیری"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "دارک"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "فندقی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "name" => "آلبالویی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "name" => "موزی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "name" => "توت فرنگی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "name" => "قهوه"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "فوری توت فرنگی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "فوری شکلاتی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "فوری موزی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "فوری وانیلی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "فوری قهوه"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "غیر فوری قهوه"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "غیر فوری موز"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "غیر فوری وانیلی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "غیر فوری زعفران"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "غیر فوری شکلاتی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "غیر فوری توت فرنگی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "غیر فوری شله زرد"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "name" => "غیر فوری فرنی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "پرتغالی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "نارگیلی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "پک 12 عددی شیری"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "پک 12 عددی دارک"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => false, "name" => "پک 12 عددی فندق"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => false, "name" => "پک 12 عددی پرتغال"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => false, "name" => "پک 12 عددی نارگیل"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "سلکت فندقی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "سلکت کاپوچینو"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "سلکت شیرنارگیل"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "سلکت توت فرنگی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "سلکت پرتغال"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "سلکت موز"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "name" => "سلکت هفت لایه فندقی"),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "name" => "ویفر با کرم موزی",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "name" => "ویفر با کرم پرتغال",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "name" => "ویفر با کرم نارگیل",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "name" => "ویفر با کرم وانیل",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "name" => "ویفر با کرم شکلات",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => true, "name" => "پرلیز",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => true, "name" => "پرلیز بلیستر",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => false, "name" => "پرلیز بلیستر مینی",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => true, "name" => "دراژه با مغز بادام زمینی کوپا",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم نعنا قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم دارچین قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم نعنا 10 عددی بلستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم دارچین 10 عددی بلستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم توت فرنگی قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم سیب قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم اکالیپتوس قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم طالبی قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم استوایی قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم توت فرنگی 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم سیب ترش 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم اکالیپتوس 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم طالبی 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم استوایی 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم نعنا قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم دارچین قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم توت فرنگی قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم سیب قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم استوایی قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم اکالیپتوس قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "name" => "با طعم طالبی قوطی 24 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا گندم", "is_main" => true, "name" => "با طعم نارگیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا گندم", "is_main" => true, "name" => "با طعم پرتغال",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "name" => "با تزئین کنجد و شوید و طعم هل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "name" => "با تزئین کنجد و طعم پرتغال",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "name" => "با تزئین کنجد و طعم نارگیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "name" => "با تزئین کنجد و شوید و طعم هل 500 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "name" => "با تزئین کنجد و طعم پرتغال 500 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "name" => "با تزئین کنجد و طعم نارگیل 500 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "name" => "چند غله دارچین",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "name" => "چند غله قهوه",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "name" => "چند غله وانیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "name" => "چند غله زنجبیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "name" => "چند غله سبوس دار",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "name" => "شکلاتی",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "name" => "آلبالو",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "name" => "نارگیل",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "name" => "پرتغال",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "کاکائویی کرم دار وانیلی گرد",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "کاکائویی کرم دار وانیلی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "کرم دار کاکائویی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "کرم دار نارگیلی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "کرم دار توت فرنگی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "کرم دار پرتغالی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "دایجستیو",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "مینی دایجستیو",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "مینی دایجستیو توت فرنگی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "مینی دایجستیو پرتغال",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "مینی دایجستیو نارگیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "مینی دایجستیو کاپوچینو",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "مینی دایجستیو موز",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "مینی دایجستیو کاکائویی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "name" => "کینگ",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "name" => "بارسلونا",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "name" => "بایرن مونیخ",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "name" => "رئال مادرید",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "name" => "لیورپول",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "name" => "منچستر یونایتد",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "name" => "یوونتوس",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "انار",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "انبه",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "آلبالو",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "آلوئه ورا",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "آناناس",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "بلوبری",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "پرتغال",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "توت فرنگی",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "طالبی",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "موز",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "هلو",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "name" => "کوپا کولا",),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "name" => "شکلاتی",),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "name" => "وانیلی",),
            array("name" => "مرغ", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("name" => "جو", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("name" => "سبزی", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("name" => "قارچ", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("name" => "گوشت", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("name" => "مرغ", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("name" => "سبزیجات", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("name" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("name" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("name" => "پک 5 عددی سبزیجات", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("name" => "پک 5 عددی قارچ", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("name" => "گوشت", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "مرغ", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "کاری", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "سبزیجات", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "مرغ و گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "پک 5 عددی گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "پک 5 عددی کاری", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "پک 5 عددی سبزیجات", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "پک 5 عددی مرغ و گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "گوشت", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("name" => "مرغ", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("name" => "سبزیجات", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("name" => "قارچ", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("name" => "کاری", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("name" => "پیتزا", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("name" => "میگو", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("name" => "گوشت", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("name" => "مرغ", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("name" => "سبزیجات", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("name" => "بوقلمون", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("name" => "مرغ کاری", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("name" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("name" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("name" => "پک 5 عددی سبزی", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("name" => "پک 5 عددی بوقلمون", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("name" => "پک 5 عددی مرغ و کاری", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("name" => "گوشت", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("name" => "مرغ", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("name" => "سبزی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("name" => "تند و فلفلی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("name" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("name" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("name" => "پک 5 عددی سبزی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("name" => "پک 5 عددی تند و فلفلی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("name" => "مرغ", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("name" => "جو", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("name" => "قارچ", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("name" => "سبزیجات", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("name" => "ورمیشل", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("name" => "مرغ با ورمیشل", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("name" => "جو با قارچ", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("name" => "ماکارونی با سبزیجات", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("name" => "مرغ و ورمیشل", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "جو", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "قارچ", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "سبزیجات", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "جو و قارچ", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("name" => "دال عدس", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("name" => "مرغ", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("name" => "جو", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("name" => "قارچ", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("name" => "سبزیجات", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("name" => "جو و قارچ", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("name" => "مرغ", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("name" => "بره", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("name" => "گوساله", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("name" => "سبزیجات", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("name" => "زعفران", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("name" => "پک 48 عددی", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("name" => "ساشه مرغ", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "ساشه گوساله", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "ساشه پیاز", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "ساشه گوجه فرنگی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "ساشه زعفران", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "مرغ 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "گوساله 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "پیاز 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "گوجه فرنگی 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "زعفران 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("name" => "مرغ 2 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("name" => "گوساله 2 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("name" => "سبزیجات 2 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("name" => "گوساله 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("name" => "مرغ 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("name" => "بوقلمون 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("name" => "سبزیجات 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("name" => "مرغ 60 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("name" => "گوساله 60 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("name" => "مرغ 500 گرمی", "type" => "عصاره", "brand" => "ایپک", "is_main" => false),
            array("name" => "گوساله 500 گرمی", "type" => "عصاره", "brand" => "ایپک", "is_main" => false),
            array("name" => "chocotrips", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("name" => "winkers", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("name" => "trips فندقی", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("name" => "trips شیری", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("name" => "tickers", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("name" => "شیری", "type" => "ویفر", "brand" => "چیچک", "is_main" => false),
            array("name" => "قهوه", "type" => "ویفر", "brand" => "چیچک", "is_main" => false),
            array("name" => "دارک", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("name" => "فندقی", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("name" => "peralus شیری", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("name" => "peralus دارک", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("name" => "شکلاتی فندقی", "type" => "ویفر", "brand" => "بایکیت", "is_main" => false),
            array("name" => "شکلاتی تلخ", "type" => "ویفر", "brand" => "بایکیت", "is_main" => false),
            array("name" => "شکلاتی بادام زمینی", "type" => "ویفر", "brand" => "بایکیت", "is_main" => false),
            array("name" => "فندقی", "type" => "ویفر", "brand" => "شونیز", "is_main" => false),
            array("name" => "تلخ", "type" => "ویفر", "brand" => "شونیز", "is_main" => false),
            array("name" => "کره بادام زمینی", "type" => "ویفر", "brand" => "شونیز", "is_main" => false),
            array("name" => "چند غله", "type" => "بیسکوئیت", "brand" => "ستاک", "is_main" => false),
            array("name" => "چند غله", "type" => "بیسکوئیت", "brand" => "جمانه", "is_main" => false),
            array("name" => "کرم دار دربیس", "type" => "بیسکوئیت", "brand" => "درنا", "is_main" => false),
            array("name" => "سبوس دار", "type" => "بیسکوئیت", "brand" => "گرجی", "is_main" => false),
            array("name" => "پذیرایی", "type" => "بیسکوئیت", "brand" => "فرخنده", "is_main" => false),
            array("name" => "پذیرایی", "type" => "بیسکوئیت", "brand" => "سلامت", "is_main" => false),
            array("name" => "مرغ", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("name" => "سبزی", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("name" => "جو", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("name" => "قارچ", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("name" => "ورمیشل", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
        );
        foreach ($json as $item) {
            Product::create($item);
        }
    }
    public function fix2(Request $request)
    {
        try {
            $dir= "images/".$request['dir'];
            $dirlist = scandir($dir);
            for ($i=2; $i<count($dirlist); $i++){
                (new ImageController)->resizeImage($dir.'/',$dirlist[$i]);
            }
            echo "<pre>",print_r(scandir($dir)),"</pre>";
        }catch (\Exception $exception){
            return $exception;
        }
    }
}
