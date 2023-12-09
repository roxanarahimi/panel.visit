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
            $pages_count = ceil($data->total() / $perPage);
            $labels = [];
            for ($i = 1; $i <= $pages_count; $i++) {
                (array_push($labels, $i));
            }
            return response([
                "data" => ProductResource::collection($data),
                "pages" => $pages_count,
                "total" => $data->total(),
                "labels" => $labels,
                "title" => 'محصولات',
                "tooltip_new" => 'ثبت محصول جدید',
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
            } elseif ($request['stock' == 'limited']) {
                $data = $data->where('stock', '>', 0)->where('stock', '<', 5);
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
        } catch (Exception $exception) {
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
            $product = Product::create($request->except('image', 'related_products'));
            if ($request['image']) {
                $name = 'product_' . $product['id'] . '_' . uniqid() . '.png';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/products/');
                $product->update(['image' => '/' . $image_path]);

                (new ImageController)->resizeImage('images/products/', $name);
            }
            if ($request['related_products']) {
                foreach ($request['related_products'] as $item) {
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
            $product->update($request->except('image', 'related_products'));
            if ($request['image']) {
                $name = 'product_' . $product['id'] . '_' . uniqid() . '.png';
                $image_path = (new ImageController)->uploadImage($request['image'], $name, 'images/products/');

                if ($product['image']) {
                    $file_to_delete = ltrim($product['image'], $product['image'][0]); //remove '/' from file name start
                    $file_to_delete_thumb = ltrim(str_replace('.png', '_thumb.png', $file_to_delete));
                    if (file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                    if (file_exists($file_to_delete_thumb)) {
                        unlink($file_to_delete_thumb);
                    }
                }

                $product->update(['image' => '/' . $image_path]);
                (new ImageController)->resizeImage('images/products/', $name);


            }

            $relatedZ = RelatedProduct::where('product_id', $request['id'])->get();
            foreach ($relatedZ as $item) {
                $item->delete();
            }

            if ($request['related_products']) {
                foreach ($request['related_products'] as $item) {
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
            foreach ($relatedZ as $item) {
                $item->delete();
            }
            if ($product['image']) {
                $file_to_delete = ltrim($product['image'], $product['image'][0]); //remove '/' from file name start
                $file_to_delete_thumb = ltrim(str_replace('.png', '_thumb.png', $file_to_delete));
                if (file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
                if (file_exists($file_to_delete_thumb)) {
                    unlink($file_to_delete_thumb);
                }
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

    public function updateOrder(Request $request, Product $product)
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
            $data = Product::orderBy('title')->where('product_category_id', $id)->where('active', 1)->get();


            return response(["data" => ProductResource::collection($data)], 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function byCat($id)
    {
        try {
            $data = Product::orderBy('id')->where('product_category_id', $id)->where('active', 1)->get();

            foreach ($data as $item) {
                $thumb2 = $item->image ? str_replace('.png', '_thumb.png', $item->image) : '';
                $item->thumb = $thumb2;
            }
            return response([
                "data" => $data,

            ], 200);
        } catch (\Exception $exception) {
            return response($exception);

        }
    }

    public function fix3(Request $request)
    {
        $json = array(
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "مرغ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "سبزیجات",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "جو و قارچ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "جو و گوجه فرنگی",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "مرغ با ورمیشل",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "جو",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "قارچ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "پیاز فرانسوی",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "جو و قارچ با خامه الیت پلاس",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "مرغ و سبزیجات الیت پلاس",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "سبزیجات و ورمیشل الیت پلاس",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "لیوانی قارچ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "لیوانی سبزیجات",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "لیوانی مرغ",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "مرغ و ذرت",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "پودر حلیم",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "پودر سوخاری",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "کتلت و همبرگر",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "تیلیت",),
            array("type" => "سوپ", "brand" => "الیت", "is_main" => true, "title" => "سس بشامل",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "جو",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "مرغ",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "مرغ و ورمیشل",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "قارچ",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "سبزیجات",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "جو و قارچ",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => true, "title" => "جو و گوجه فرنگی",),
            array("type" => "سوپ", "brand" => "آماده لذیذ", "is_main" => false, "title" => "پودر سوخاری",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "مرغ",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "سبزی",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "جو و قارچ",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "مرغ و ورمیشل",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "جو",),
            array("type" => "سوپ", "brand" => "نودیلند", "is_main" => true, "title" => "قارچ",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "گوشت",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "مرغ",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "سبزیجات",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "گوجه تند",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "قارچ و پنیر",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "قارچ",),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "زعفران"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "کاری"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "ماسالا"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "باربیکیو"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "کودک سبزی"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "کودک گوشت"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "کودک مرغ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "لیوانی سبزیجات"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "لیوانی گوشت"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "لیوانی مرغ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی گوشت"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی مرغ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی سبزی"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی گوجه تند"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی قارچ"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی قارچ و پنیر"),
            array("type" => "نودل", "brand" => "الیت", "is_main" => true, "title" => "پک 5 عددی باربیکیو"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک گوشت"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک مرغ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک سبزیجات"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک قارچ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک قارچ و پنیر"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک گوجه تند"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "کودک کاری"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی گوشت"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی مرغ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی سبزی"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی قارچ"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی قارچ و پنیر"),
            array("type" => "نودل", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک 5 عددی گوجه"),
            array("type" => "نودل", "brand" => "نودیلند", "is_main" => true, "title" => "گوشت"),
            array("type" => "نودل", "brand" => "نودیلند", "is_main" => true, "title" => "مرغ"),
            array("type" => "نودل", "brand" => "نودیلند", "is_main" => true, "title" => "سبزی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "مرغ 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "بره 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "گوساله 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "کاری 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "برنج 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "لیمو عمانی 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "سیر 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "سبزیجات 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "پیاز 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "قارچ 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "جوجه 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "گوجه فرنگی 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "زعفران 8 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "مرغ 12 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "بره 12 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "گوساله 12 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "پک زعفران 3 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "پک مرغ 48 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "پک بره 48 عددی"),
            array("type" => "عصاره", "brand" => "الیت", "is_main" => true, "title" => "پک گوساله 48 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "گوساله 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "بره 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "مرغ 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "زعفران 8 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک مرغ 48 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک بره 48 عددی"),
            array("type" => "عصاره", "brand" => "آماده لذیذ", "is_main" => true, "title" => "پک گوساله 48 عددی"),
            array("type" => "عصاره", "brand" => "نودیلند", "is_main" => true, "title" => "گوساله"),
            array("type" => "عصاره", "brand" => "نودیلند", "is_main" => true, "title" => "مرغ"),
            array("type" => "عصاره", "brand" => "نودیلند", "is_main" => true, "title" => "بره"),
            array("type" => "پرک", "brand" => "الیت", "is_main" => true, "title" => "جو پرک"),
            array("type" => "پرک", "brand" => "الیت", "is_main" => true, "title" => "گندم پرک"),
            array("type" => "آش", "brand" => "الیت", "is_main" => true, "title" => "جو"),
            array("type" => "آش", "brand" => "الیت", "is_main" => true, "title" => "رشته"),
            array("type" => "آش", "brand" => "الیت", "is_main" => true, "title" => "سبزی"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "چاشنی خوراک با زعفران"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "چاشنی خورشت با زعفران"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "چاشنی خوراک با کاری"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "چاشنی خورشت قیمه"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "چاشنی خورشت قورمه سبزی"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "فلفل سیاه"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "فلفل قرمز"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "فلفل سفید"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "سماق"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "سیر"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "زردچوبه"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر سالاد"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر کباب"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر ماست"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر لیمو و فلفل"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر دارچین"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر کاری"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "آویشن"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر زنجبیل"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر پاپریکا"),
            array("type" => "ادویه", "brand" => "الیت", "is_main" => true, "title" => "پودر پیاز"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => true, "title" => "گوجه و ریحان"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => true, "title" => "قارچ و پنیر"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => false, "title" => "ریحان با خامه"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => false, "title" => "سس بلونیز"),
            array("type" => "پاستا", "brand" => "الیت", "is_main" => false, "title" => "کاری با خامه"),
            array("type" => "پیاز داغ", "brand" => "الیت", "is_main" => true, "title" => "پیاز داغ"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کریمر متوسط"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کریمر بزرگ"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کونیگ 90"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کونیگ 170"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی شکلاتی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی آیرش کریم"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی بدون قند"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی فندقی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی وانیلی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی کلاسیک"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "باکسی هات چاکلت"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی کلاسیک"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی فندقی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی وانیلی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی هات چاکلت"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی بدون قند"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی آیرش کریم"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کیسه 40 عددی شکلاتی"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کلاسیک 40 عددی عقابی آروما"),
            array("type" => "قهوه", "brand" => "کوپا", "is_main" => true, "title" => "کاپوچینو"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "کاکائویی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "عسلی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "کاکائو ذرت"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "بالشتی شکلاتی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "حلقه ای شکلاتی"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "کورن فلکس مورنینگ لایت"),
            array("type" => "غلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "کورن فلکس اسپشیال سی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "دو رنگ 100 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "فندقی 100 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "دو رنگ 200 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "فندقی 200 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "دو رنگ 330 گرمی"),
            array("type" => "شکلات صبحانه", "brand" => "کوپا", "is_main" => true, "title" => "فندقی 330 گرمی"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "مینی کانتی شیری"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "مینی کانتی دارک"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "کانتی شیری"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "کانتی دارک"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "کانتی نعنایی"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "کانتی پرتغالی"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "پک کانتی شیری"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => true, "title" => "پک کانتی دارک"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => false, "title" => "پک کانتی با طعم نعنا"),
            array("type" => "ویفر", "brand" => "کانتی", "is_main" => false, "title" => "پک کانتی با طعم پرتغالی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "شیری"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "دارک"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "فندقی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "آلبالویی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "موزی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "توت فرنگی"),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "قهوه"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "فوری توت فرنگی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "فوری شکلاتی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "فوری موزی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "فوری وانیلی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "فوری قهوه"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری قهوه"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری موز"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری وانیلی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری زعفران"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری شکلاتی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری توت فرنگی"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری شله زرد"),
            array("type" => "پودینگ", "brand" => "کوپا", "is_main" => true, "title" => "غیر فوری فرنی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "پرتغالی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "نارگیلی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "پک 12 عددی شیری"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "پک 12 عددی دارک"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => false, "title" => "پک 12 عددی فندق"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => false, "title" => "پک 12 عددی پرتغال"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => false, "title" => "پک 12 عددی نارگیل"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت فندقی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت کاپوچینو"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت شیرنارگیل"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت توت فرنگی"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت پرتغال"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت موز"),
            array("type" => "ویفر", "brand" => "کوپا", "is_main" => true, "title" => "سلکت هفت لایه فندقی"),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "title" => "ویفر با کرم موزی",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "title" => "ویفر با کرم پرتغال",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "title" => "ویفر با کرم نارگیل",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "title" => "ویفر با کرم وانیل",),
            array("type" => "ویفر", "brand" => "تامبی", "is_main" => true, "title" => "ویفر با کرم شکلات",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => true, "title" => "پرلیز",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => true, "title" => "پرلیز بلیستر",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => false, "title" => "پرلیز بلیستر مینی",),
            array("type" => "دراژه", "brand" => "کوپا", "is_main" => true, "title" => "دراژه با مغز بادام زمینی کوپا",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم نعنا قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم دارچین قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم نعنا 10 عددی بلستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم دارچین 10 عددی بلستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم توت فرنگی قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم سیب قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم اکالیپتوس قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم طالبی قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم استوایی قوطی بزرگ",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم توت فرنگی 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم سیب ترش 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم اکالیپتوس 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم طالبی 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم استوایی 10 عددی بلیستر",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم نعنا قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم دارچین قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم توت فرنگی قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم سیب قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم استوایی قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم اکالیپتوس قوطی 24 گرمی",),
            array("type" => "آدامس", "brand" => "اکس فست", "is_main" => true, "title" => "با طعم طالبی قوطی 24 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا گندم", "is_main" => true, "title" => "با طعم نارگیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا گندم", "is_main" => true, "title" => "با طعم پرتغال",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و شوید و طعم هل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و طعم پرتغال",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و طعم نارگیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و شوید و طعم هل 500 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و طعم پرتغال 500 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا جو", "is_main" => true, "title" => "با تزئین کنجد و طعم نارگیل 500 گرمی",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "title" => "چند غله دارچین",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "title" => "چند غله قهوه",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "title" => "چند غله وانیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "title" => "چند غله زنجبیل",),
            array("type" => "بیسکوئیت پذیرایی", "brand" => "کوپا", "is_main" => true, "title" => "چند غله سبوس دار",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "title" => "شکلاتی",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "title" => "آلبالو",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "title" => "نارگیل",),
            array("type" => "بیسکوئیت", "brand" => "او کوپا", "is_main" => true, "title" => "پرتغال",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کاکائویی کرم دار وانیلی گرد",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کاکائویی کرم دار وانیلی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کرم دار کاکائویی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کرم دار نارگیلی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کرم دار توت فرنگی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کرم دار پرتغالی مستطیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "دایجستیو",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو توت فرنگی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو پرتغال",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو نارگیلی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو کاپوچینو",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو موز",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "مینی دایجستیو کاکائویی",),
            array("type" => "بیسکوئیت", "brand" => "کوپا", "is_main" => true, "title" => "کینگ",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "بارسلونا",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "بایرن مونیخ",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "رئال مادرید",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "لیورپول",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "منچستر یونایتد",),
            array("type" => "ویفر", "brand" => "کلاب", "is_main" => true, "title" => "یوونتوس",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "انار",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "انبه",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "آلبالو",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "آلوئه ورا",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "آناناس",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "بلوبری",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "پرتغال",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "توت فرنگی",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "طالبی",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "موز",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "هلو",),
            array("type" => "ژله", "brand" => "کوپا", "is_main" => true, "title" => "کوپا کولا",),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "شکلاتی",),
            array("type" => "پودینگ", "brand" => "فیت و فان", "is_main" => true, "title" => "وانیلی",),
            array("title" => "مرغ", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "جو", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "سبزی", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "قارچ", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "گوشت", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "مرغ", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "سبزیجات", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "پک 5 عددی سبزیجات", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "پک 5 عددی قارچ", "type" => "نودل", "brand" => "مهنام", "is_main" => false),
            array("title" => "گوشت", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "مرغ", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "کاری", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "سبزیجات", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "مرغ و گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی کاری", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی سبزیجات", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پک 5 عددی مرغ و گوجه", "type" => "نودل", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "گوشت", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "مرغ", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "سبزیجات", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "قارچ", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "کاری", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "پیتزا", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "میگو", "type" => "نودل", "brand" => "گلین", "is_main" => false),
            array("title" => "گوشت", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "مرغ", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "سبزیجات", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "بوقلمون", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "مرغ کاری", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "پک 5 عددی سبزی", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "پک 5 عددی بوقلمون", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "پک 5 عددی مرغ و کاری", "type" => "نودل", "brand" => "هفده", "is_main" => false),
            array("title" => "گوشت", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "مرغ", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "سبزی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "تند و فلفلی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "پک 5 عددی گوشت", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "پک 5 عددی مرغ", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "پک 5 عددی سبزی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "پک 5 عددی تند و فلفلی", "type" => "نودل", "brand" => "شف هو", "is_main" => false),
            array("title" => "مرغ", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "جو", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "قارچ", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "سبزیجات", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "ورمیشل", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "مرغ با ورمیشل", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "جو با قارچ", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "ماکارونی با سبزیجات", "type" => "سوپ", "brand" => "مهنام", "is_main" => false),
            array("title" => "مرغ و ورمیشل", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "جو", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "قارچ", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "سبزیجات", "type" => "سوپ", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "جو و قارچ", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "دال عدس", "type" => "سوپ", "brand" => "زر", "is_main" => false),
            array("title" => "مرغ", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("title" => "جو", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("title" => "قارچ", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("title" => "سبزیجات", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("title" => "جو و قارچ", "type" => "سوپ", "brand" => "سبزان", "is_main" => false),
            array("title" => "مرغ", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "بره", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "گوساله", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "سبزیجات", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "زعفران", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "پک 48 عددی", "type" => "عصاره", "brand" => "مهنام", "is_main" => false),
            array("title" => "ساشه مرغ", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "ساشه گوساله", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "ساشه پیاز", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "ساشه گوجه فرنگی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "ساشه زعفران", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "مرغ 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "گوساله 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "پیاز 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "گوجه فرنگی 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "زعفران 8 عددی", "type" => "عصاره", "brand" => "هاتی کارا", "is_main" => false),
            array("title" => "مرغ 2 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "گوساله 2 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "سبزیجات 2 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "گوساله 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "مرغ 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "بوقلمون 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "سبزیجات 6 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "مرغ 60 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "گوساله 60 عددی", "type" => "عصاره", "brand" => "زر", "is_main" => false),
            array("title" => "مرغ 500 گرمی", "type" => "عصاره", "brand" => "ایپک", "is_main" => false),
            array("title" => "گوساله 500 گرمی", "type" => "عصاره", "brand" => "ایپک", "is_main" => false),
            array("title" => "chocotrips", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("title" => "winkers", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("title" => "trips فندقی", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("title" => "trips شیری", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("title" => "tickers", "type" => "ویفر", "brand" => "زر", "is_main" => false),
            array("title" => "شیری", "type" => "ویفر", "brand" => "چیچک", "is_main" => false),
            array("title" => "قهوه", "type" => "ویفر", "brand" => "چیچک", "is_main" => false),
            array("title" => "دارک", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("title" => "فندقی", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("title" => "peralus شیری", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("title" => "peralus دارک", "type" => "ویفر", "brand" => "باراکا", "is_main" => false),
            array("title" => "شکلاتی فندقی", "type" => "ویفر", "brand" => "بایکیت", "is_main" => false),
            array("title" => "شکلاتی تلخ", "type" => "ویفر", "brand" => "بایکیت", "is_main" => false),
            array("title" => "شکلاتی بادام زمینی", "type" => "ویفر", "brand" => "بایکیت", "is_main" => false),
            array("title" => "فندقی", "type" => "ویفر", "brand" => "شونیز", "is_main" => false),
            array("title" => "تلخ", "type" => "ویفر", "brand" => "شونیز", "is_main" => false),
            array("title" => "کره بادام زمینی", "type" => "ویفر", "brand" => "شونیز", "is_main" => false),
            array("title" => "چند غله", "type" => "بیسکوئیت", "brand" => "ستاک", "is_main" => false),
            array("title" => "چند غله", "type" => "بیسکوئیت", "brand" => "جمانه", "is_main" => false),
            array("title" => "کرم دار دربیس", "type" => "بیسکوئیت", "brand" => "درنا", "is_main" => false),
            array("title" => "سبوس دار", "type" => "بیسکوئیت", "brand" => "گرجی", "is_main" => false),
            array("title" => "پذیرایی", "type" => "بیسکوئیت", "brand" => "فرخنده", "is_main" => false),
            array("title" => "پذیرایی", "type" => "بیسکوئیت", "brand" => "سلامت", "is_main" => false),
            array("title" => "مرغ", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("title" => "سبزی", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("title" => "جو", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("title" => "قارچ", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
            array("title" => "ورمیشل", "type" => "سوپ", "brand" => "برتر", "is_main" => false),
        );
        foreach ($json as $item) {
            Product::create($item);
        }
    }

    public function fix(Request $request)
    {

        $array = [
            ["name"=> "سارا ماله میر"],
            ["name"=> "حسام انصاری"],
            ["name"=> "مهدی چیرانی"],
            ["name"=> "مرتضی رجبی"],
            ["name"=> "الشن پورحسن"],
            ["name"=> "علی مختاری"],
            ["name"=> "حمید جعفری"],
            ["name"=> "مسعود موسوی"],
            ["name"=> "محمدرضا قنواتی"],
            ["name"=> "میلاد همتی"],
            ["name"=> "علی خرمی پور"]

];
        foreach ($array as $item) {
            \App\Models\Visitor::create($item);
        }
        $info = array(
            array("title" => "شرکت کالا بهرسان تارا نوین شعبه شیراز", "province_id" => 16, "shop_category_id" => 15, "grade_id" => 4),
            array("title" => "فروشگاه ماف پارس (هایپر استار)  - لواسان", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 4),
            array("title" => "هایپر می اکباتان/ مگامال تهران", "province_id" => 8, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "هایپرسان نجم خاورمیانه تهران", "province_id" => 8, "shop_category_id" => 4, "grade_id" => 1),
            array("title" => "هایپراستار ارم ( باکری جنوب )", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "هایپر استار صبا", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "هایپراستار اصفهان", "province_id" => 4, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "هایپر استار شیراز", "province_id" => 16, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "شرکت خدمات کالاي شهروند (لواسان", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "هایپراستار کوروش (تهران)", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 4),
            array("title" => "هایپر استار تیراژه 2 (تهران)", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 2),
            array("title" => "اتکا شهید امامی نسب (امامی نسب تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهدای انقلاب (شهدای انقلاب تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا قصرفیروزه (قصر فیروز تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "هایپر می سبحان", "province_id" => 8, "shop_category_id" => 11, "grade_id" => 2),
            array("title" => "اتکا فروشگاه شهید دکتر چمران (چمران تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "هایپرسان رشت", "province_id" => 30, "shop_category_id" => 4, "grade_id" => 1),
            array("title" => "اتکا شهید فلاحی (فلاحی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهدای سوم خرداد (فلاحی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهید رجایی (رجایی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید بروجردی (رجایی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهید کوچک افشاری (فلاحی تهران)/اتکا دردشت", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا نوبنیاد (نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهرک شهید محلاتی (نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید کمیل یارمحمدی (کرج)/اتکا کرج", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهدای فردیس (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهید نامجو (نامجو تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید همتی سمنان (سمنان)", "province_id" => 14, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهدای مارلیک (کرج)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا مهرآباد (مهرآباد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا توحید (مهرآباد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت کالا بهرسان تارا نوین بندرعباس", "province_id" => 22, "shop_category_id" => 15, "grade_id" => 4),
            array("title" => "اتکا شهدای رودهن (شهدای انقلاب تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید فکوری (فکوری تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهدای قم (قم)", "province_id" => 18, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا ولایت (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا فتح المبین (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهدای ارومیه (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا کرمانشاه (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهدای آذربایجان (تبریز)/اتکا تبریز", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید نظری مهرشهر (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهید سعادت یار (نوبنیاد تهران)/اتکا لویزان", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهدای گلستان (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید مختاری (فلاحی تهران)/اتکا کوهک", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "سی یار گستر ایرانیان-هایپرسی اندیشه 1 (کرج)", "province_id" => 5, "shop_category_id" => 14, "grade_id" => 2),
            array("title" => "اتکا شماره دو گرگان (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ساری (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهدای هرمزگان (بندرعباس)/اتکا بندر عباس", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید سردار قاسم سلیمانی (اهواز)/اتکا اهواز", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید ابراهیمی خرم آباد (خرم آباد)", "province_id" => 19, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید گودرزی بروجرد (خرم آباد)", "province_id" => 19, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اورست آل این آل لاله پارک تبریز", "province_id" => 1, "shop_category_id" => 13, "grade_id" => 1),
            array("title" => "اتکا شهدای زرهی (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا کرمان (کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا اراک (اراک)", "province_id" => 21, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید شهریاری زنجان (زنجان)/اتکا زنجان", "province_id" => 13, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا قزوین (قزوین)", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید مدنی همدان (همدان)", "province_id" => 23, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا ایلام (ایلام)", "province_id" => 6, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهدای گمنام شهریار (مهرآباد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا گنبد (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا آزادشهر گلستان (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا علی آباد کتول (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرمی اطلس تبریز", "province_id" => 1, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "هایپر می پاژ (مشهد)", "province_id" => 10, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "هایپر می عظیمیه (کرج)", "province_id" => 5, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "هایپر می اصفهان", "province_id" => 4, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "هایپر می نمک آبرود(پانوراما)", "province_id" => 20, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "هایپر می آجودانیه", "province_id" => 8, "shop_category_id" => 11, "grade_id" => 2),
            array("title" => "اتکا هوایی شهید دوران (شیراز)/اتکا هوایی فارس", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای رشت (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا امام خمینی (نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای هشتگرد (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا سنندج (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "شرکت خدمات کالای شهروند (صادقیه", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 1),
            array("title" => "شرکت خدمات کالای شهروند (بوستان )", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 1),
            array("title" => "شرکت خدمات کالای شهروند (کاشانک", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 3),
            array("title" => "شرکت خدمات کالای شهروند ( آل احمد )", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 2),
            array("title" => "شرکت خدمات کالای شهروند (چیذر )", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 3),
            array("title" => "شرکت خدمات کالای شهروند (بهاران )", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 2),
            array("title" => "شرکت خدمات کالای شهروند (بهرود", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 1),
            array("title" => "شرکت خدمات کالای شهروند (بیهقی", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 1),
            array("title" => "شرکت خدمات کالای شهروند ( آزادگان", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 1),
            array("title" => "شرکت خدمات کالای شهروند(تهران نو )", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 3),
            array("title" => "شرکت خدمات کالای شهروند (لویزان )", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 2),
            array("title" => "شرکت خدمات کالای شهروند( ایران زمین", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 1),
            array("title" => "شرکت خدمات کالای شهروند - (راه آهن", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "شرکت خدمات کالای شهروند (هفت حوض", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 3),
            array("title" => "شرکت خدمات کالای شهروند( والفجر", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "شرکت خدمات کالای شهروند (حکیمیه", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 1),
            array("title" => "شرکت خدمات کالای شهروند (شهرری", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 2),
            array("title" => "شرکت خدمات کالای شهروند ( مبعث", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "شرکت خدمات کالای شهروند( آزادی", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "شرکت خدمات کالای شهروند (المپیک", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 2),
            array("title" => "اتکا شهدای رستم آباد (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت خدمات کالای شهروند (نبرد )", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 2),
            array("title" => "شرکت خدمات کالای شهروند (متروصادقیه", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "شرکت خدمات کالای شهروند (خانی آباد", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 2),
            array("title" => "شرکت خدمات کالای شهروند-جنت آباد", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 2),
            array("title" => "اتکا الزهرا (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "هایپراستار پاسداران", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 3),
            array("title" => "شرکت خدمات کالای شهروند( طیب", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "شرکت خدمات کالای شهروند ( نفت", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 3),
            array("title" => "شرکت خدمات کالای شهروند (ملک", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 3),
            array("title" => "اتکا شهید رییسعلی دلواری (بوشهر)/اتکا بوشهر", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا یاسوج (یاسوج)", "province_id" => 28, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "شرکت خدمات کالای شهروند ( دارآباد", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "اتکا شهدای غواص (فکوری تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "شرکت خدمات کالای شهروند (لواسان", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "اتکا دو هزار واحدی (رجایی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا پارچین (رجایی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید رستمی (بجنورد)/اتکا بجنورد", "province_id" => 11, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "هایپرمی هاشمیه مشهد", "province_id" => 10, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "هایپرمی سنتر خزر تنکابن", "province_id" => 20, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "شرکت خدمات کالای شهروند (پرند", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 3),
            array("title" => "شرکت خدمات کالای شهروند (عدل", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "هایپرمی طوبی", "province_id" => 8, "shop_category_id" => 11, "grade_id" => 2),
            array("title" => "اتکا یزد (یزد)", "province_id" => 31, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا ولیعصر نسیم شهر (مهرآباد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت خدمات کالای شهروند(تهرانسر", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 3),
            array("title" => "هایپرمی نیکامال", "province_id" => 5, "shop_category_id" => 11, "grade_id" => 3),
            array("title" => "اتکا شهدای گمنام رباط کریم (مهرآباد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "هایپرمی آمل", "province_id" => 20, "shop_category_id" => 11, "grade_id" => 3),
            array("title" => "اتکا اردبیل (اردبیل)", "province_id" => 3, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا پایگاه هوایی تبریز (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا مرند (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای مراغه (مراغه)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "فروشگاه سپه (رودکی )", "province_id" => 8, "shop_category_id" => 10, "grade_id" => 4),
            array("title" => "اتکا خاورشهر (رجایی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فروشگاه سپه (آزادی)", "province_id" => 8, "shop_category_id" => 10, "grade_id" => 4),
            array("title" => "فروشگاه سپه (ویلا)", "province_id" => 8, "shop_category_id" => 10, "grade_id" => 4),
            array("title" => "اتکا شهید کاظم بازیار (بندرعباس)/اتکا امام علی", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فروشگاه سپه (پیروزی )", "province_id" => 8, "shop_category_id" => 10, "grade_id" => 4),
            array("title" => "فروشگاه سپه (دولت )", "province_id" => 8, "shop_category_id" => 10, "grade_id" => 4),
            array("title" => "فروشگاه سپه (شریف )", "province_id" => 8, "shop_category_id" => 10, "grade_id" => 4),
            array("title" => "فروشگاه سپه کرج", "province_id" => 5, "shop_category_id" => 10, "grade_id" => 4),
            array("title" => "اتکا اتکا یاغچیان (تبریز)/اتکا گلشهر", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای سهند (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهرکرد (شهرکرد)", "province_id" => 24, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا سخایی(پیشوا)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهدای آستانه اشرفیه (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا لاهیجان (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا لنگرود (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای بندر انزلی (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهدای رودسر (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای اندیشه (مهرآباد تهران)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ورامین (پیشوا)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا تنکابن (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای ماسال (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای صومعه سرا (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپراستار رباط کریم", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 3),
            array("title" => "اتکا شهید زرهرن شاهرود (سمنان)", "province_id" => 14, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا ساوه (اراک)", "province_id" => 21, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "هایپرمی مهرشهر ( کرج)", "province_id" => 5, "shop_category_id" => 11, "grade_id" => 3),
            array("title" => "اتکا شهید کاوه بیرجند (بیرجند)", "province_id" => 9, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهدای اصفهان (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شاهین شهر (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا نجف آباد (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهدای گرمسار (سمنان)", "province_id" => 14, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای رحیمی دزفول (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا بعثت خرمشهر (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا صاحب الامر (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "هایپرمی فردیس", "province_id" => 5, "shop_category_id" => 11, "grade_id" => 2),
            array("title" => "هایپراستار افرا", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 3),
            array("title" => "اتکا شعبه دو زنجان (زنجان)", "province_id" => 13, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهدای ماهشهر (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن گنبد (گلستان)", "province_id" => 29, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا بهارستان (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا قائم شهر (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپراستار جماران", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 3),
            array("title" => "اتکا صدرا (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا بیست و یک حمزه (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا میانه (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا سراب (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا زعفرانیه (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا آمل (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهید مرادپور (اهواز)/اتکا شوشتر", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای آباده (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "سی یار گستر ایرانیان-هایپرسی اندیشه 2 (کرج)", "province_id" => 8, "shop_category_id" => 14, "grade_id" => 4),
            array("title" => "اتکا اهر (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن شاندیز مشهد", "province_id" => 10, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "پلاس وان مشهد", "province_id" => 10, "shop_category_id" => 8, "grade_id" => 4),
            array("title" => "اتکا یاس (نامجو تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای گمنام پرند (مهرآباد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهید درخشان (مهرآباد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا الغدیر (قم)", "province_id" => 18, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا بابل (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا چالوس (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا نوهد (مهرآباد تهران)/اتکا پرندک", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای بهبهان (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شبستر (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بم (کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا سیرجان (کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا هوانیروز (کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا ابهر (زنجان)", "province_id" => 13, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شماره دو بروجرد (خرم آباد)", "province_id" => 19, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهید شوشتری (زاهدان)/اتکا مرکزی زاهدان (زاهدان)", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید ستوده فسا (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا امیرکبیر کاشان (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "فامیلی مدرن امام خمینی(قم)", "province_id" => 18, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا شهید جولائی (امامی نسب تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شاهرود2 (سمنان)", "province_id" => 14, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهرک آزادی (فکوری تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا جام جم (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا حکیمیه (قصر فیروز تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای لشگر (سنندج)/اتکا سنندج2", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید فلاحی مشهد (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "سپه حر", "province_id" => 8, "shop_category_id" => 10, "grade_id" => 4),
            array("title" => "اتکا شهید تهرانی مقدم (نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا محلات (اراک)", "province_id" => 21, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپر استار یزد", "province_id" => 31, "shop_category_id" => 2, "grade_id" => 2),
            array("title" => "اتکا شهدای یاسوج (یاسوج)", "province_id" => 28, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا دلیجان (راک)", "province_id" => 21, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا مهاباد (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "هایپر استار اکومال (کرج)", "province_id" => 5, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "اتکا اشرفی اصفهانی2 (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید ستاری قرچک - سامان (پیشوا)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن سمنان", "province_id" => 14, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا شهدای گلپایگان (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا داران (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ولیعصر (مراغه)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا عجبشیر (مراغه)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ملکان (مراغه)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهرضا (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهید ناخدا همتی (بوشهر)/اتکا دریایی ارتش", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای بناب (مراغه)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا آبادان (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید فلاحی مسجدسلیمان (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا کلاچای (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا رشت2 (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن بجنورد", "province_id" => 11, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا شهدای تالش (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت خدمات کالای شهروند(تهرانپارس)", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "هایپراستار ایران مال", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "هایپرفامیلی مشهد آرلتون", "province_id" => 10, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهید خلبان بابایی (بوشهر)/اتکا هوایی", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا خمین (اراک)", "province_id" => 21, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فروشگاه فامیلی مدرن یزد", "province_id" => 31, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا شهدای رامهرمز (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا سوسنگرد (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرمی  کیا مال", "province_id" => 5, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "اتکا کازرون (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرمی فرشته", "province_id" => 8, "shop_category_id" => 11, "grade_id" => 3),
            array("title" => "اتکا اردستان (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "سی یار گستر ایرانیان-هایپرسی شهریار", "province_id" => 5, "shop_category_id" => 14, "grade_id" => 3),
            array("title" => "اتکا ثامن الحجج (مشهد)/اتکا مرکزی مشهد", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا جهرم (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن قم شعبه 2", "province_id" => 18, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "شهروند دروس (شرکت خدماتی کالای شهروند)", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "فامیلی مدرن پروما مشهد", "province_id" => 10, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا ساری2 (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا نور (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا بابلسر (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ایرانشهر (زاهدان)", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا پایگاه هوایی کنارک (سیستان و بلوچستان)/ شعبه1", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا پایگاه دریایی کنارک (سیستان و بلوچستان)/ شعبه2", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا چابهار (سیستان و بلوچستان)", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن فریدون کنار", "province_id" => 20, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "هایپرمی پارسه (تهران)", "province_id" => 8, "shop_category_id" => 11, "grade_id" => 3),
            array("title" => "اتکا شهدای ارتش کرمانشاه (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا سلماس (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا آذرشهر (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا خرمدره (زنجان)", "province_id" => 13, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا گچساران (یاسوج)", "province_id" => 28, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا دهدشت (یاسوج)", "province_id" => 28, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا زابل (سیستان و بلوچستان)", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا سقز (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بانه (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید ورمقانی (سنندج)/اتکا قروه", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای بیجار (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا کامیاران (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مریوان (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بانه 2 (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا دیوان دره (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای سریش آباد (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهرک باغمیشه (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا ایلخچی (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای خطیب (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "فامیلی مدرن نیشابور", "province_id" => 10, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا لشگر (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا پایگاه هوایی شهید بابایی (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا میبد (یزد)", "province_id" => 31, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا لاله غربی (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا صوفیان (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شاهین دژ (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا میاندوآب (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا گلشهر (مراغه)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای عجبشیر (مراغه)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی ساعی خمینی شهر اصفهان", "province_id" => 4, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهدای باغملک (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بیمارستان شریعتی (نامجو تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا برازجان (بوشهر)", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "فامیلی مدرن مبارکه اصفهان", "province_id" => 4, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهدای شیروان (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فروشگاه  فامیلی امام علی", "province_id" => 31, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "فامیلی مدرن دشتی (یزد)", "province_id" => 31, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهدای کمالشهر (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "فامیلی مدرن دانشجو (یزد)", "province_id" => 31, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "فامیلی مدرن دهه فجر (یزد)", "province_id" => 31, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا ملایر (همدان)", "province_id" => 23, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای نهاوند (همدان)", "province_id" => 23, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید نوژه (همدان)", "province_id" => 23, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مولوی (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا بعثت (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا ظفر (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهرک پردیس (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا گیلانغرب (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا هوانیروز کرمانشاه (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید شیرودی (کرمانشاه)/اتکا هرسین", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا اسلام آباد غرب (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا جانبازان پاوه (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا سنقر (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای صحنه (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای جوانرود (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا کنگاور (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای چالدران (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا فاطمی (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بعثت (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا همایون ویلا (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا امیرکبیر (کرج)/اتکا کیانمهر", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپراستار اوپال", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 2),
            array("title" => "اتکا قائم (کرج)/اتکا گوهردشت", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بروجرد3 (خرم آباد)", "province_id" => 19, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن قوچان", "province_id" => 10, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "فامیلی مدرن کیانی", "province_id" => 4, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا قیدار (زنجان)", "province_id" => 13, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن گرمدره", "province_id" => 5, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا شهدای بابلسر (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شهروند شمیران سنتر(شرکت خدماتی کالای شهروند)", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "اتکا شهدای بازارچه سیلاب (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شهروند مهرشهر(شرکت خدماتی کالای شهروند)", "province_id" => 5, "shop_category_id" => 1, "grade_id" => 2),
            array("title" => "اتکا شهدا باشت (یاسوج)", "province_id" => 28, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدا دهدشت (یاسوج)", "province_id" => 28, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپراستار  تهران پارس", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 2),
            array("title" => "اتکا شهدای گچساران (یاسوج)", "province_id" => 28, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای بهمئی (یاسوج)", "province_id" => 28, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ایلام2 (ایلام)", "province_id" => 6, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ولیعصر (ایلام)", "province_id" => 6, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا عبدالصباح امینیان (ایلام)", "province_id" => 6, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید محمد نبی شمسی (ایلام)", "province_id" => 6, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید حیدر قلی ربیعی سرابله (ایلام)", "province_id" => 6, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای ایوانغرب (ایلام)", "province_id" => 6, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای آبدانان (ایلام)", "province_id" => 6, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید مرادی (رجایی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بهشهر (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید توسلی خورموج (بوشهر)", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای مال خلیفه (یاسوج)", "province_id" => 28, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا سرو آباد (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا آل محمد (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای روانسر (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن شهر ری", "province_id" => 8, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهدای مرودشت (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای ارسنجان (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا زیتون کارمندی (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا هلال بهبهان (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا قوچان (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهید احمدی جوان (بوشهر)/اتکا عالیشهر", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فروشگاه امیران( حصارک)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "فروشگاه امیران(فردیس)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 6),
            array("title" => "فروشگاه امیران(کردان)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "اتکا نیشابور (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای خوانسار (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا گناباد (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای نکا (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا قاسم ابن الحسن (شهدای انقلاب تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید شیردل (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای هفتکل (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید علی لندی (اهواز)/اتکا ایذه", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای پدافند هوایی (مشهد)/اتکا رادار", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا زرند (کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای هشترود (مراغه)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید برونسی (مشهد)/اتکا تربت حیدریه (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "فروشگاه زنجیره امیران(بلوارارم)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 6),
            array("title" => "فروشگاه امیران(فاز 4 مهرشهر )", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "فروشگاه امیران (فرهنگیان-خیابان قزوین)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "فروشگاه امیران (فاز 1 اندیشه)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 6),
            array("title" => "فروشگاه امیران (مهرشهر)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "فروشگاه امیران (بلوار انقلاب)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "فروشگاه امیران (عظیمیه-میخک)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 6),
            array("title" => "فروشگاه امیران (باغستان)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "اتکا شهید وطن یاری (اهواز)/اتکا دشت آزادگان", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید قهرمانی (بندرعباس)/اتکا هدیش", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای خسروشاه (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا الهیه کرمانشاه (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای نورآباد (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا فجر (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای میانه2 (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا هوا نیروز (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا امام خمینی زرین شهر (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "فروشگاه زنجیره ای امیران(مشکین دشت)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "اتکا شهرک فرهنگیان (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "امیران ملارد(دفتر مرکزی)", "province_id" => 8, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "اتکا قلم (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای قشم (قشم)", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "فامیلی مدرن الغدیر (اصفهان)", "province_id" => 4, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا پاکدشت (پیشوا)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فروشگاه امیران(درختی)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 6),
            array("title" => "اتکا گیلاوند (شهدای انقلاب تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مسکن (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا سراوان (سیستان و بلوچستان)", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپر استار قم", "province_id" => 18, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "اتکا شهدای سقز (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ستاد کل (فلاحی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید زنگنه (کرج)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا گلوگاه (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای جهرم (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید اخلاص طلب (بندرعباس)/اتکا هوایی", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای خلیج فارس (بوشهر)/اتکا ریشهر", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای تکاب (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا فاز 4 مهرشهر (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن کرمان", "province_id" => 26, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا بلوار کشاورز (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید حامدی (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ماهدشت (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپراستار مارلیک (کرج)", "province_id" => 5, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "اتکا کاشمر (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا گلشهر (زنجان)", "province_id" => 13, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا فارسان (شهرکرد)", "province_id" => 24, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید علیزاده دامغان (سمنان)", "province_id" => 14, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید دانشگر سمنان (سمنان)", "province_id" => 14, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا رسالت (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا میناب (بندرعباس)", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مطهری رودهن (نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید ورمزیار (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای اندیمشک(اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا نصر (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای مبارکه (الزهرا اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا حافظ اسلامشهر (اتکا رجایی)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا سبزوار (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا ثامن الحجج(مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا جیرفت (کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای دامغان (سمنان)", "province_id" => 14, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای سبزوار (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای نیرو انتظامی (اتکا نامجو)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا امامت مشهد (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهدای پارس آباد (اردبیل)", "province_id" => 3, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای کیش (بندر عباس)", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید صیاد شیرازی (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای کنگان (بوشهر)", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای رودان (بندرعباس)", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای بندر لنگه (بندر عباس)", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای نطنز (اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا جاسک (بندر عباس)", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای فسا فرانچایز (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن بابل", "province_id" => 20, "shop_category_id" => 7, "grade_id" => 2),
            array("title" => "اتکا طالقانی کرمانشاه (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید محبعلی فارسی (سیستان و بلوچستان)", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا کردکوی (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید واحدی (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا سپیدار (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا گاوگان (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مهر سهند (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای تنگستان اهرم (بوشهر)", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بهاران (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا اندیشه زنجان (زنجان)", "province_id" => 13, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ابهر 2 (زنجان)", "province_id" => 13, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای یکه دکان (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا امام حسن داراب (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا دانشگاه (نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید کاظمی (نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا فردوسی نیشابور (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا کریمی (اراک)", "province_id" => 21, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا خاش (زاهدان)", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مشگین شهر (اردبیل)", "province_id" => 3, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا میرجاوه (زاهدان)", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای وحدت (زاهدان)/اتکا وحدت زابل", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "هایپراستار (ارومیه)", "province_id" => 2, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "اتکا شهید ساوجی (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا الهیه مشهد (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا سپاه (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای مهرویلا (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مایان (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهرک صابر (مهرآباد)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا امام رضا (شهدای انقلاب تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا حاجیان (پیشوا)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا آبیک (قزوین)/اتکا ایرج عیوضی", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای پرواز (اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا جوادالائمه (اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا فدک (بوشهر)", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن شریعتی (تهران)", "province_id" => 8, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهرک امید (نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا گنجینه کیش (بندرعباس)", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای کوچصفهان (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا روانسر2 (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن (تبریز)", "province_id" => 1, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهدای مزرعه نمونه (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای مینو دشت (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای گالیکش (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ولیعصر 2 (مراغه)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا آشخانه (بجنورد)", "province_id" => 11, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای پیرانشهر (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا اصلاندوز (اردبیل)", "province_id" => 3, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا پارس آباد 2 (اردبیل)", "province_id" => 3, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا نقده (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا کوثر (شهدای انقلاب تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهرک شهید بهشتی (نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهید باقری (قزوین)", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن امیرکبیر (کاشان)", "province_id" => 4, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا هامون (زاهدان)", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شازند (اراک)", "province_id" => 21, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شیخ نوایی (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپراستار بوتانیکال (مشهد)", "province_id" => 10, "shop_category_id" => 2, "grade_id" => 4),
            array("title" => "اتکا شهدای اروند آبادان(اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا وحدت گنبد (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید صفوی (بوشهر)", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهید زواری (رجایی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بهداری (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ثامن (فلاحی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا صدا و سیما (نامجو تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا هاشمیه مشهد (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "اتکا شهید سلیمانی هشتگرد (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا لنگرود 1 (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا خوی (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "فامیلی مدرن (بناب)", "province_id" => 1, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهدای نمین (اردبیل)", "province_id" => 3, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا کیاشهر (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا نجیرم (بوشهر)", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا محمود آباد (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ماندستان (بوشهر)", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا چمستان (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا دهگلان (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرمی محمودآباد (مازندران)", "province_id" => 20, "shop_category_id" => 11, "grade_id" => 4),
            array("title" => "اتکا شهدای جویبار (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپراستار (قزوین)", "province_id" => 17, "shop_category_id" => 2, "grade_id" => 3),
            array("title" => "اتکا سلمان فارسی (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای املش (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهرقدس (فکوری تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن سیرجان (کرمان)", "province_id" => 26, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا جامی سنندج (سنندج)", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بروجن (شهرکرد)", "province_id" => 24, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید دقایقی (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا کهنوج (کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای پلدشت (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای ماکو (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا جوادآباد -سامان (پیشوا)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید حسینی سرخس (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای پردیس (شهدای انقلاب)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن (قم)", "province_id" => 18, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا شهدای سلامت (قزوین)", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای کلاله (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرمی اردبیل (اردبیل)", "province_id" => 3, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "اتکا اندیشه مشهد (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای رفسنجان (کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای فومن (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای ارتش (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت خدماتی کالای شهروند(بهشت)", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "اتکا ولایت خرم آباد (خرم آباد)", "province_id" => 19, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا میدان امام حسین (خرم آباد)", "province_id" => 19, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا قائم کرمانشاه (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای حاجی آباد (بندرعباس)", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای گمنام(نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای دشتستان برازجان(بوشهر)", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا  فارابی (نامجو)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای فاضل آباد(گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید فلاح نژاد (فکوری تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا پردیس اهواز (اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای فریدونکنار(ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپراستار هدیش(تهران)", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 4),
            array("title" => "اتکا شهدای خان ببین (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا امام خمینی ماهشهر (اهواز)/اتکا مشارکتی", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا طب کودکان(اتکا نامجو)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن زاهدان(زاهدان)", "province_id" => 15, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "فامیلی رفسنجان(کرمان)", "province_id" => 26, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا شهدای نوشهر(ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بهروش (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا گلستان( شهید یاسینی)(امامی نسب)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای اکبرآباد(شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا نیکشهر فرانچایز(زاهدان)", "province_id" => 15, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید بابائی (قزوین)/اتکا محمدیه", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا اندیشه (تبریز)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای قائمشهر(ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید ابراهیم هادی (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا رحمانی(قزوین)", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا الوند(قزوین)", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای گلبهار (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مسیر نفت (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای  مهران(ایلام)", "province_id" => 6, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا لردگان (شهرکرد)", "province_id" => 24, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید ابوالحسنی(ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا الهیه پاکدشت (رجایی)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرمی پلاتین (تهران)", "province_id" => 8, "shop_category_id" => 11, "grade_id" => 4),
            array("title" => "اسپار پاسداران", "province_id" => 8, "shop_category_id" => 6, "grade_id" => 4),
            array("title" => "اتکا شهرک گلستان(شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید منتظری(کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا الغدیر(کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای کهنوج(کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای بافت(کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید پور جعفری(کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید باهنر(کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مالک اشتر(کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا کاشانی تربت حیدریه (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی سعیدیه(همدان)", "province_id" => 23, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا شهدای حفاظت هواپیمایی (فکوری تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید سلیمی پور(رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپر فامیلی گلشهر (مشهد)", "province_id" => 10, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا ولیعصر تربت حیدریه (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرفامیلی لرستان (خرم آباد)", "province_id" => 19, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهیدمیرزاقاسمی(خرم آباد)", "province_id" => 19, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای آران و بیدگل (اصفهان)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای فریمان (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید قنبری (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرمی یزد (یزد)", "province_id" => 31, "shop_category_id" => 11, "grade_id" => 3),
            array("title" => "شرکت زنجیره غذایی امیران (بلوار انقلاب)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "اتکا امت مشهد (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت زنجیره غذایی امیران (عظیمیه)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 6),
            array("title" => "شرکت زنجیره غذایی امیران  (فاز یک اندیشه)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 6),
            array("title" => "شرکت زنجیره غذایی امیران(بلوار ارم)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 6),
            array("title" => "شرکت زنجیره غذایی امیران (فاز 4 مهرشهر)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "شرکت زنجیره غذایی امیران  (فردیس)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 6),
            array("title" => "اتکا شهدای لاهیجان(رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت زنجیره غذایی امیران(مهرشهر)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "شرکت زنجیره غذایی امیران (درختی)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 6),
            array("title" => "شرکت زنجیره غذایی امیران ملارد (دفتر مرکزی)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "شرکت زنجیره غذایی امیران (مشکین دشت)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "شرکت زنجیره غذایی امیران(فرهنگیان)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "شرکت زنجیره غذایی امیران (باغستان)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "شرکت زنجیره غذایی امیران(میدان آزادگان-برغان)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "شرکت زنجیره غذایی امیران(کردان)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "شرکت زنجیره غذایی امیران( حصارک)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "اتکا حکیم نظامی (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهدای خان طومان آمل (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت آتیه فرزندان وطن (پروژه باقری - وال مارکت)", "province_id" => 8, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "شرکت آتیه فرزندان وطن(پروژه مهراد مال - وال مارکت)", "province_id" => 5, "shop_category_id" => 3, "grade_id" => 3),
            array("title" => "شرکت آتیه فرزندان وطن(پروژه بهشتی - وال مارکت)", "province_id" => 5, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "هایپر فامیلی رباط کریم(تهران)", "province_id" => 8, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "هایپرفامیلی ساری(ساری)", "province_id" => 20, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "هایپرفامیلی بابلسر(بابلسر)", "province_id" => 20, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهدای مرصاد (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا خدا پرست  (پیشوا)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید نجفی (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای منوجان (بندرعباس)", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا فرانچایز امینی بیات(قم)", "province_id" => 18, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا فرانچایز ناجیان سبز(قم)", "province_id" => 18, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ولیعصر نجف آباد (ولیعصر نجف آباد)اصفهان", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا طالقانی قوچان (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید باکری (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت خدماتی کالای شهروند(علی اکبری-سهروردی)", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "فامیلی مدرن  سبزوار(مشهد)", "province_id" => 10, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "فامیلی مدرن الهیه( مشهد)", "province_id" => 10, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا شهدای بیست و هشت دی (سنندج)/اتکا زاگرس", "province_id" => 25, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید نوری (شهدای انقلاب)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بعثت گنبد(گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا فرانچایز دلیجان (اراک)", "province_id" => 21, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرمی سنندج (سنندج)", "province_id" => 25, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "هایپرمی درختی (کرج)/مهرویلا", "province_id" => 5, "shop_category_id" => 11, "grade_id" => 3),
            array("title" => "اتکا شهرک شهید رسولی (نوبنیاد تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای بهشهر (ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای زاوه( مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی چمران کرمان(کرمان)", "province_id" => 26, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا جزیره خارک (بوشهر)", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا سردارجنگل لاهیجان (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای بندرترکمن(گرگان)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت آتیه فرزندان وطن(مترو نبرد - وال مارکت)", "province_id" => 8, "shop_category_id" => 3, "grade_id" => 3),
            array("title" => "اتکا شهید چمران دورود (خرم اباد)", "province_id" => 19, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای بندر گز(گرگان)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید رضاپور (مهراباد)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهیدان نژاد فلاح (کرج)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت کالابهرسان تارا نوین (شیراز)", "province_id" => 16, "shop_category_id" => 15, "grade_id" => 4),
            array("title" => "شرکت کالابهرسان تارا نوین(بندرعباس)", "province_id" => 22, "shop_category_id" => 15, "grade_id" => 4),
            array("title" => "اتکا فاطمیه سیرجان (کرمان)", "province_id" => 26, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید قلیزاده (رجایی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا رزمندگان ارتش - سامان (پیشوا)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن( رامسر)", "province_id" => 30, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "شرکت آتیه فرزندان وطن(وال مارکت قزوین)", "province_id" => 17, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "هایپر استار (یزد)", "province_id" => 31, "shop_category_id" => 2, "grade_id" => 2),
            array("title" => "فرانچایز اتکا قرنی مشهد", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا 2 محمدیه(قزوین)", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا ارباب پور (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مدرس (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت آتیه فرزندان وطن(وال مارکت زنجان)", "province_id" => 13, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "اتکا شهدای آستارا (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای دو کوهه اندیمشک(اهواز)", "province_id" => 12, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بلوار طبرسی (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا محمد رسول الله سعدآباد (بوشهر)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهرک پژوهش (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکاتالش لیسار(رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی  مدرن امیر کبیر (کرمان)", "province_id" => 26, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "هایپرمی شیراز", "province_id" => 16, "shop_category_id" => 11, "grade_id" => 1),
            array("title" => "شرکت آتیه فرزندان وطن(وال مارکت توحید)", "province_id" => 5, "shop_category_id" => 3, "grade_id" => 3),
            array("title" => "اتکا شهدای استهبان(شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "شرکت آتیه فرزندان وطن(اندیشه - وال مارکت)", "province_id" => 5, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "اتکا  شهریار(مهرآبادتهران)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "هایپراستار فدک (اصفهان)", "province_id" => 4, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "اتکا غفور جدی اردبیل", "province_id" => 3, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپر استار مایلی سوهانک(تهران)", "province_id" => 8, "shop_category_id" => 2, "grade_id" => 4),
            array("title" => "شرکت خدماتی کالای شهروند همت(بشرا)", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "هایپراستار پارک سنتر(اهواز)", "province_id" => 12, "shop_category_id" => 2, "grade_id" => 1),
            array("title" => "هایپراستار مارکت خلیج فارس (اهواز)", "province_id" => 12, "shop_category_id" => 2, "grade_id" => 3),
            array("title" => "اتکا جانبازان خلخال (اردبیل)", "province_id" => 3, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا مالک اشتر (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا تکاور کرمانشاه", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید زرهرن (", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فروشگاه اتکا جامی (ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا انبار مرکزی فکوری (فکوری تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 1),
            array("title" => "اتکا شهید حسین لشگری (قزوین)", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "وال مارکت رشت", "province_id" => 30, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "اتکا سفیر امید تبریز (", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فروشگاه اتکا شهید دوست زاده اسفراین", "province_id" => 11, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدا کیان (شهرکرد)", "province_id" => 4, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپر فامیلی (طرشت)", "province_id" => 8, "shop_category_id" => 7, "grade_id" => 2),
            array("title" => "اتکا شهید محمد علی صفا (بجنورد)", "province_id" => 11, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا شهید مدرس (/اتکا مدرس شیراز", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای وزارت امور خارجه", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "وال مارکت (استاد معین)", "province_id" => 8, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "اتکا شهدای رودبار", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن ایلام", "province_id" => 6, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "وال مارکت بروجرد", "province_id" => 19, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "اتکا حفاظت اطلاعات (فرودگاه مهرآباد)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا فروشگاه سربازان گمنام(ارومیه)", "province_id" => 2, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا بلوار معلم مرند (آذربایجان شرقی)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای البرز", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "فامیلی مدرن رویان", "province_id" => 20, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا مشارکتی شهدای اقلید ( استان فارس)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فروشگاه وال مارکت ساوه", "province_id" => 21, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "هایپرفامیلی مدرن قزوین", "province_id" => 8, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهید نورخدا موسوی(خرم آباد)", "province_id" => 19, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای شلمان (رشت)", "province_id" => 30, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکاشهید سعادتمند", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا امام خمینی بوشهر (بندر بوشهر)/چاه مبارک", "province_id" => 7, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای نیرو هوایی ارتش ( قصرفیروزه)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای چناران", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا درگز(مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "مایا پیروزی (مشهد)", "province_id" => 10, "shop_category_id" => 8, "grade_id" => 4),
            array("title" => "وال مارکت اصفهان - خیابان میثم", "province_id" => 4, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "وال مارکت خرم آباد - گلدشت", "province_id" => 19, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "فروشگاه هایپرفامیلی سامعی (یزد )", "province_id" => 31, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا بلوار توس (مشهد)", "province_id" => 10, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپر فامیلی دزفول", "province_id" => 12, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "فامیلی مدرن بجنورد قیام", "province_id" => 10, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "فامیلی مدرن بجنورد امیریه", "province_id" => 10, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "هایپر فامیلی تاکستان (قزوین)", "province_id" => 17, "shop_category_id" => 7, "grade_id" => 3),
            array("title" => "اتکا شهید عباسپور (قصر فیروزه تهران)/اتکا حکیمیه", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای اسلامشهر (رجایی تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "وال مارکت اردبیل", "province_id" => 3, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "اتکا مشارکتی سلفچگان (قم)", "province_id" => 18, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرمی رزمال (تهران)", "province_id" => 8, "shop_category_id" => 11, "grade_id" => 3),
            array("title" => "فامیلی رفسنجان 2 (کرمان)", "province_id" => 26, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "هایپرمی انزلی (گیلان)", "province_id" => 30, "shop_category_id" => 11, "grade_id" => 3),
            array("title" => "اتکا شهید آبشناسان (فکوری تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "وال مارکت زاهدان", "province_id" => 15, "shop_category_id" => 3, "grade_id" => 3),
            array("title" => "اتکا شهدای یافت آباد (فکوری تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا بلوار کاج (فکوری تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "وال مارکت اراک (پارک آزادی)", "province_id" => 21, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "شرکت زنجیره غذایی امیران (چمن-مهرشهر)", "province_id" => 5, "shop_category_id" => 5, "grade_id" => 5),
            array("title" => "فامیلی جیرفت(استان کرمان)", "province_id" => 26, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهدای ینگی امام (کرج)/اتکا مدافع حرم", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای دماوند (شهدای انقلاب)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "فامیلی مدرن ورامین (تهران)", "province_id" => 8, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا شهدای آق قلا (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای انبارالوم (گرگان)", "province_id" => 29, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید عفیفی پور (شیراز)", "province_id" => 16, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا تاکستان (قزوین)", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا الیاس چگینی (قزوین)", "province_id" => 17, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "هایپرفامیلی مهریز ( یزد)", "province_id" => 31, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "وال مارکت ماهشهر (خوزستان)", "province_id" => 12, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "هایپرفامیلی جهاد (کاشان)", "province_id" => 4, "shop_category_id" => 7, "grade_id" => 4),
            array("title" => "اتکا مشارکتی شهید خزلی (فکوری تهران)", "province_id" => 5, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهید حاصلی (خرم آباد)", "province_id" => 19, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "وال مارکت نازی آباد _بازاردوم (تهران)", "province_id" => 8, "shop_category_id" => 3, "grade_id" => 4),
            array("title" => "اتکا شهید صفوی کوهسار (نامجو تهران)", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا امیرکبیرمراغه (مراغه)", "province_id" => 1, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا حضرت رسول جوانرود (کرمانشاه)", "province_id" => 27, "shop_category_id" => 9, "grade_id" => 2),
            array("title" => "هایپرمی اطلس مال (تهران)", "province_id" => 8, "shop_category_id" => 11, "grade_id" => 3),
            array("title" => "اتکا امام حسن مجتیی قشم (قشم)", "province_id" => 22, "shop_category_id" => 9, "grade_id" => 4),
            array("title" => "اتکا شهدای شیرگاه(ساری)", "province_id" => 20, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "اتکا امام زاده حسن", "province_id" => 8, "shop_category_id" => 9, "grade_id" => 3),
            array("title" => "شهروند (وزارت راه)", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 4),
            array("title" => "شرکت خدمات کالای شهروند (چیتگر)", "province_id" => 8, "shop_category_id" => 1, "grade_id" => 3),
            array("title" => "فامیلی مدرن چابهار(سیستان)", "province_id" => 15, "shop_category_id" => 7, "grade_id" => 3),
        );
        foreach ($info as $item){
            \App\Models\Shop::create($item);
        }

        $brands = [
            ["title"=> "الیت"],
            ["title"=> "آماده لذیذ"],
            ["title"=> "نودیلند"],
            ["title"=> "کوپا"],
            ["title"=> "او کوپا"],
            ["title"=> "کانتی"],
            ["title"=> "تامبی"],
            ["title"=> "کلاب"],
            ["title"=> "کوپا جو"],
            ["title"=> "کوپا گندم"],
            ["title"=> "اکس فست"],

            ["title"=> "مهنام"],
            ["title"=> "هاتی کارا"],
            ["title"=> "زر"],
            ["title"=> "سبزان"],
            ["title"=> "برتر"],
            ["title"=> "گلین"],
            ["title"=> "شف هو"],
            ["title"=> "هفده"],
            ["title"=> "ایپک"],
            ["title"=> "چیچک"],
            ["title"=> "باراکا"],
            ["title"=> "بایکیت"],
            ["title"=> "شونیز"],
            ["title"=> "ستاک"],
            ["title"=> "جمانه"],
            ["title"=> "درنا"],
            ["title"=> "گرجی"],
            ["title"=> "فرخنده"],
            ["title"=> "سلامت"]
        ];
        foreach ($brands as $item){
            \App\Models\Brand::create($item);
        }
    }

    public function fix2(Request $request)
    {
        try {
            $dir = "images/" . $request['dir'];
            $dirlist = scandir($dir);
            for ($i = 2; $i < count($dirlist); $i++) {
                (new ImageController)->resizeImage($dir . '/', $dirlist[$i]);
            }
            echo "<pre>", print_r(scandir($dir)), "</pre>";
        } catch (\Exception $exception) {
            return $exception;
        }
    }
}
