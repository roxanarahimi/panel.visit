<template>
    <transition name="route" mode="out-in" appear>
        <section class="" style="text-align: justify">
            <div class="row " v-if="data">
                <div class="col-12 ">
                    <div class="row px-3 ">
                        <div class="col-12 mb-3 ">
                            <div class="d-inline-block mt-5">
                                <h3 class="mb-2 fw-bold d-block">{{ data.title }}</h3>
                            </div>
                            <router-link :to="'/panel/edit/brand/'+data.id" class="text-dark">
                <span title="ویرایش محصول"
                      class="mx-3 p-2 d-inline-block align-middle bg-dark text-light rounded-circle">
                    <i class="bi bi-pencil p-0 edit-pen"></i>
                </span>
                            </router-link>
                        </div>
                    </div>
                </div>
                <div class="row">

                </div>

                <div class="col-xxl-12 mb-5 ">
                    <div class="card h-100">
                        <div class="card-body p-md-5 ">
                            <h5 class="mb-5">دسته بندی محصولات</h5>


                            <button v-for="item in data.categories" :key="item.id" class="m-2 btn btn-sm btn-primary">{{ item.category.title}}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else><p class="fw-bold">این برند موجود نیست</p></div>

        </section>

    </transition>

</template>

<script>
import App from '../App';
import Draggable from "vuedraggable";
import Images from "../../components/Images";

export default {
    components: {App, Draggable, Images},
    data: function () {
        return {
            data: {},
            id: this.$route.params.id,
            features: [],
            images: [],
        }
    },
    created() {
        this.loadBrand();

    },
    methods: {
         loadBrand() {
             axios.get('/api/panel/brand/' + this.id)
                .then((response) => {
                    this.data = response.data;

                    console.log(this.data)
                });

        },

    }
}
</script>
<style scoped>
.color-span {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    margin-bottom: -5px;
}

#content p {
    text-align: justify !important;
    line-height: 30px !important;
    padding-left: 10px;

}

#content figure {
    text-align: center !important;
    display: block !important;
}

#content figure figcaption {
    text-align: center !important;
}

.index_image .label {
    display: block;
    font-size: 20px;
    margin: -50px 20px 20px 20px !important;
}

#content figure img {
    max-width: 100%;
}

/*.index_image span{*/
/*    display: inline-block;*/
/*    right:20px;*/
/*    bottom: 20px;*/
/*}*/
.edit-pen {
    font-size: 12px;
    margin: 0 6px !important;
}

.element-cards {
    /*box-shadow: none;*/
    /*background-color: transparent;*/
}

.moving-card {
    opacity: 1 !important;
    background-color: whitesmoke;
    border: dotted dimgray !important;
    box-shadow: none;

}

th, td {
    padding: 10px;
    min-width: 100px;
}
</style>
