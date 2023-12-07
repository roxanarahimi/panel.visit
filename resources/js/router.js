import {createRouter, createWebHistory} from 'vue-router'

import Login from "./components/panel/admin/Login";

import UserEdit from "./components/panel/user/UserEdit";
import SlideCreate from "./components/panel/slide/SlideCreate";
import SlideEdit from "./components/panel/slide/SlideEdit";

import Profile from "./components/panel/admin/Profile";
import Error404 from "./components/panel/error/Error404";

const routes = [
    //panel

    {
        path: "/panel",
        // name: "Home",
        component: () => import(/* webpackChunkName: "home" */ '../js/components/panel/Home'),

    },
    {
        path: "/panel/categories",
        //     component: () => import(/* webpackChunkName: "test" */ '../js/components/panel/product/Categories'),
        // name: "Categories",
        component: () => import(/* webpackChunkName: "Categories" */ '../js/components/panel/Categories'),
        props: true,
        params: true
    },
    {
        path: "/panel/priority/products",
        component: () => import(/* webpackChunkName: "productPriority" */  './components/panel/product/ProductsPriority'),
        name: "productPriority",
        params: true,
        props: true,
    },
    {
        path: "/panel/products",
        component: () => import(/* webpackChunkName: "productAllData" */  './components/panel/allData'),
        name: "productAllData",
        params: true,
        props: true,
    },
    {
        path: "/panel/new/product",
        name: "ProductCreate",
        component: () => import(/* webpackChunkName: "ProductCreate" */ '../js/components/panel/product/ProductCreate'),
        // component: ProductCreate,
        params: true
    },
    {
        path: "/panel/edit/product/:id",
        name: "ProductEdit",
        component: () => import(/* webpackChunkName: "ProductEdit" */ '../js/components/panel/product/ProductEdit'),
        params: true
    },
    {
        path: "/panel/product/:id",
        name: "Product",
        component: () => import(/* webpackChunkName: "Product" */ '../js/components/panel/product/Product'),
        params: true

    },

    {
        path: "/panel/orders",
        component: () => import(/* webpackChunkName: "orderAllData" */  './components/panel/allData'),
        name: 'orderAllData',
        params: true,
        props: true,
        // component: Orders,
    },
    {
        path: "/panel/new/order",
        name: "OrderCreate",
        component: () => import(/* webpackChunkName: "OrderCreate" */ '../js/components/panel/order/OrderCreate'),
        params: true
    },
    {
        path: "/panel/edit/order/:id",
        name: "OrderEdit",
        component: () => import(/* webpackChunkName: "OrderEdit" */ '../js/components/panel/order/OrderEdit'),
        params: true
    },
    {
        path: "/panel/order/:id",
        name: "Order",
        component: () => import(/* webpackChunkName: "Order" */ '../js/components/panel/order/Order'),
        params: true

    },

    {
        path: "/panel/articles",
        component: () => import(/* webpackChunkName: "articleAllData" */ './components/panel/allData'),
        name: 'articleAllData',
        params: true,
        props: true

    },
    {
        path: "/panel/new/article",
        name: "ArticleCreate",
        component: () => import(/* webpackChunkName: "ArticleCreate" */ './components/panel/article/ArticleCreate'),
        params: true
    },
    {
        path: "/panel/edit/article/:id",
        name: "ArticleEdit",
        component: () => import(/* webpackChunkName: "ArticleEdit" */ './components/panel/article/ArticleEdit'),
        params: true
    },
    {
        path: "/panel/article/:id",
        name: "Article",
        component: () => import(/* webpackChunkName: "Article" */ './components/panel/article/Article'),
    },


    {
        path: "/panel/slides",
        name: "Slides",
        component: () => import(/* webpackChunkName: "Slides" */ '../js/components/panel/slide/Slides'),

        // component: Slides,
    },
    {
        path: "/panel/new/slide",
        name: "SlideCreate",
        component: SlideCreate,
        params: true
    },
    {
        path: "/panel/edit/slide/:id",
        name: "SlideEdit",
        component: SlideEdit,
        params: true
    },
    {
        path: "/panel/food/slides",
        component: () => import(/* webpackChunkName: "foodSlideAllData" */ './components/panel/allData'),
        name: "foodSlideAllData",
        params: true,
        props: true
    },
    {
        path: "/panel/new/foodSlide",
        name: "FoodSlideCreate",
        component: () => import(/* webpackChunkName: "FoodSlideCreate" */ '../js/components/panel/foodSlide/FoodSlideCreate'),
        params: true
    },
    {
        path: "/panel/edit/food/slide/:id",
        name: "FoodSlideEdit",
        component: () => import(/* webpackChunkName: "FoodSlideEdit" */ '../js/components/panel/foodSlide/FoodSlideEdit'),

        params: true
    },
    {
        path: "/panel/food/slide/:id",
        name: "FoodSlide",
        component: () => import(/* webpackChunkName: "FoodSlideEdit" */ '../js/components/panel/foodSlide/FoodSlide'),
        params: true
    },
    {
        path: "/panel/blogs",
        component: () => import(/* webpackChunkName: "blogAllData" */ './components/panel/allData'),
        name: "blogAllData",
        params: true,
        props: true
    },
    {
        path: "/panel/new/blog",
        name: "BlogCreate",
        component: () => import(/* webpackChunkName: "BlogCreate" */ '../js/components/panel/blog/BlogCreate'),
        params: true
    },
    {
        path: "/panel/edit/blog/:id",
        name: "BlogEdit",
        component: () => import(/* webpackChunkName: "BlogEdit" */ '../js/components/panel/blog/BlogEdit'),

        params: true
    },
    {
        path: "/panel/blog/:id",
        name: "Blog",
        component: () => import(/* webpackChunkName: "Blog" */ '../js/components/panel/blog/Blog'),
        params: true
    },
    {
        path: "/panel/brands",
        component: () => import(/* webpackChunkName: "brandAllData" */ './components/panel/allData'),
        name: "brandAllData",
        params: true,
        props: true
    },
    {
        path: "/panel/new/brand",
        name: "BrandCreate",
        component: () => import(/* webpackChunkName: "BrandCreate" */ '../js/components/panel/brand/BrandCreate'),
        params: true
    },
    {
        path: "/panel/edit/brand/:id",
        name: "BrandEdit",
        component: () => import(/* webpackChunkName: "BrandEdit" */ '../js/components/panel/brand/BrandEdit'),

        params: true
    },
    {
        path: "/panel/brand/:id",
        name: "Brand",
        component: () => import(/* webpackChunkName: "Brand" */ '../js/components/panel/brand/Brand'),
        params: true
    },
    {
        path: "/panel/cities",
        component: () => import(/* webpackChunkName: "cityAllData" */ './components/panel/allData'),
        name: "cityAllData",
        params: true,
        props: true
    },
    {
        path: "/panel/new/city",
        name: "CityCreate",
        component: () => import(/* webpackChunkName: "CityCreate" */ '../js/components/panel/city/CityCreate'),
        params: true
    },
    {
        path: "/panel/edit/brand/:id",
        name: "BrandEdit",
        component: () => import(/* webpackChunkName: "CityEdit" */ '../js/components/panel/city/CityEdit'),

        params: true
    },
    {
        path: "/panel/city/:id",
        name: "Brand",
        component: () => import(/* webpackChunkName: "City" */ '../js/components/panel/city/City'),
        params: true
    },

    {
        path: "/panel/grades",
        component: () => import(/* webpackChunkName: "gradeAllData" */ './components/panel/allData'),
        name: "gradeAllData",
        params: true,
        props: true
    },
    {
        path: "/panel/new/grade",
        name: "GradeCreate",
        component: () => import(/* webpackChunkName: "GradeCreate" */ '../js/components/panel/grade/GradeCreate'),
        params: true
    },
    {
        path: "/panel/edit/grade/:id",
        name: "GradeEdit",
        component: () => import(/* webpackChunkName: "GradeEdit" */ '../js/components/panel/grade/GradeEdit'),

        params: true
    },
    {
        path: "/panel/grade/:id",
        name: "Grade",
        component: () => import(/* webpackChunkName: "Grade" */ '../js/components/panel/grade/Grade'),
        params: true
    },

    {
        path: "/panel/shops",
        component: () => import(/* webpackChunkName: "shopAllData" */ './components/panel/allData'),
        name: "shopAllData",
        params: true,
        props: true
    },
    {
        path: "/panel/new/shop",
        name: "ShopCreate",
        component: () => import(/* webpackChunkName: "ShopCreate" */ '../js/components/panel/shop/ShopCreate'),
        params: true
    },
    {
        path: "/panel/edit/shop/:id",
        name: "ShopEdit",
        component: () => import(/* webpackChunkName: "ShopEdit" */ '../js/components/panel/shop/ShopEdit'),

        params: true
    },
    {
        path: "/panel/shop/:id",
        name: "Shop",
        component: () => import(/* webpackChunkName: "Shop" */ '../js/components/panel/shop/Shop'),
        params: true
    },



    {
        path: "/panel/visitors",
        component: () => import(/* webpackChunkName: "visitorAllData" */ './components/panel/allData'),
        name: "visitorAllData",
        params: true,
        props: true
    },
    {
        path: "/panel/new/visitor",
        name: "VisitorCreate",
        component: () => import(/* webpackChunkName: "VisitorCreate" */ '../js/components/panel/visitor/VisitorCreate'),
        params: true
    },
    {
        path: "/panel/edit/visitor/:id",
        name: "VisitorEdit",
        component: () => import(/* webpackChunkName: "VisitorEdit" */ '../js/components/panel/visitor/VisitorEdit'),

        params: true
    },
    {
        path: "/panel/visitor/:id",
        name: "Visitor",
        component: () => import(/* webpackChunkName: "Visitor" */ '../js/components/panel/visitor/Visitor'),
        params: true
    },


    {
        path: "/panel/user/:id",
        name: "User",
        component: () => import(/* webpackChunkName: "User" */ '../js/components/panel/user/User'),

        // component: User,
    },
    {
        path: "/panel/users",
        component: () => import(/* webpackChunkName: "userAllData" */  './components/panel/allData'),
        name: 'userAllData',
        params: true,
        props: true,

    },
    {
        path: "/panel/edit/user/:id",
        name: "UserEdit",
        component: UserEdit,
        params: true
    },
    {
        path: "/",
        name: 'Login0',
        component: Login,
    },
    {
        path: "/panel/login",
        name: "Login",
        component: Login,
    },
    {
        path: "/panel/profile",
        name: "Profile",
        component: Profile,
    },


    {
        path: "/panel/admins",
        component: () => import(/* webpackChunkName: "adminAllData" */  './components/panel/allData'),
        name: "adminAllData",
        params: true,
        props: true

    },

    {
        path: "/panel/finance",
        component: () => import(/* webpackChunkName: "financeAllData" */  './components/panel/allData'),
        name: "financeAllData",
        params: true,
        props: true
    },

    // {
    //     path: "/sample",
    //     name: "sample",
    //     component: () => import(/* webpackChunkName: "sample" */ './components/panel/report/catSample'),
    // },
    // {
    //     path: "/chart",
    //     name: "chart",
    //     component: () => import(/* webpackChunkName: "chart" */ './components/panel/report/Chart'),
    // },
    {
        path: "/:catchAll(.*)",
        name: "Error404",
        component: Error404,
    } ,

];

const router = createRouter({
    history: createWebHistory(process.env.BASE_URL),
    routes
})

export default router
