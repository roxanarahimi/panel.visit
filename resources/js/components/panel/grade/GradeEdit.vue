<template>
    <transition name="route" mode="out-in" appear>
        <section>
            <h3 class="mb-5">ویرایش برند</h3>

            <div class="row mt-3">
                <div class="col-12 mb-3">
                    <div class="card" v-if="isDefined">
                        <div class="card-body">
                            <form id="editForm">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="title" class="form-label">عنوان</label>
                                        <input @input="updateData" type="text" :class="{hasError: errors.title}"
                                               class="form-control"
                                               id="title" :value="data.title" aria-describedby="titleHelp" required>
                                        <div id="titleHelp" class="form-text error"></div>
                                        <p class="form-text error m-0" v-for="e in errors.title">{{ e }}</p>

                                    </div>
                                    <div class="col-md-12 col-lg-12 mb-3">
                                        <label class="form-label">دسته بندی محصولات</label>
                                        <Multiselect
                                            v-model="value"
                                            :mode="'tags'"
                                            :options="allbrands"
                                            :object="true"
                                            label="title"
                                            :searchable="true"
                                            :create-option="true"
                                        />

                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <BtnSubmit @click.prevent="updateInfo">
                                            ویرایش
                                        </BtnSubmit>
                                        <!--                                        <button id="submit" class="btn btn-primary d-flex justify-content-between" @click.prevent="updateInfo" type="submit">-->
                                        <!--                                             ویرایش <loader-sm class="loader-sm d-none" />-->
                                        <!--                                        </button>-->
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </transition>
</template>

<script>
import ImageCropper from '../../components/ImageCropper';
import App from '../App';
import BtnSubmit from "../../components/BtnSubmit";
import Multiselect from '@vueform/multiselect'


export default {
    components: {ImageCropper, App, BtnSubmit, Multiselect},
    data() {
        return {
            id: this.$route.params.id,
            data: {},
            categories: [],
            errors: [],
            image_codes: [],
            image_names: [],
            imgRequired: false,
            hasCaption: false,
            aspect: false,
            isPng: true,
            isDefined: false,
            enableClick: true,
            features: [],
            sizes: [{"size": "", "dimensions": "", "color_name": "", "color_code": "", "stock": ""}],
            images: [],

            value: [],
            allbrands: []
        }
    },

    created() {
        this.loadCategories();
        this.loadbrand();
        this.loadbrands();
    },

    methods: {
        loadbrand() {

            axios.get('/api/panel/brand/' + this.id)
                .then((response) => {
                    console.log(response.data);
                    this.data = response.data;
                    if (this.data.sizes && this.data.sizes.length) {
                        this.sizes = this.data.sizes;
                    }
                    if (this.data.images) {
                        this.images = this.data.images;
                    }
                })
                .then(() => {
                    this.isDefined = true;
                })
                .then(() => {
                    this.value = this.data.related_brands;
                })
                .then(() => {
                    this.watchTextAreas();
                })
                .catch();
        },
        loadbrands() {

            axios.get('/api/panel/brand?page=1&perPage=1000&search=')
                .then((response) => {
                    this.allbrands = response.data.data;
                    this.allbrands = this.allbrands.filter((item)=>item.id != this.id);
                })
                .catch()
        },
        loadCategories() {
            axios.get('/api/panel/category/brand?page=1&perPage=100000')
                .then((response) => {
                    this.categories = response.data.data;
                })
                .catch();
        },
        updateInfo() {


            this.errors = [];
            let emptyFieldsCount = 0;

            let req = document.querySelectorAll('[required]');
            req.forEach((element) => {
                if (element.value === "") {
                    element.classList.add('hasError');
                    element.nextSibling.innerHTML = "فیلد اجباری";
                    emptyFieldsCount++;
                } else {
                    element.classList.remove('hasError');
                    element.nextSibling.innerHTML = "";
                }
            });

            if (emptyFieldsCount === 0) {
                let selectedbrands = [];
                    this.value.forEach((element)=>{
                        selectedbrands.push(element.value)
                    });

                axios.post('/api/panel/brand/' + this.$route.params.id,
                    {
                        // image: document.getElementById('Image_index_code').value,
                        id: this.$route.params.id,
                        image: document.getElementById('Image__code').value,
                        title: document.getElementById('title').value,
                        subTitle: document.getElementById('subTitle').value,
                        title_en: document.getElementById('title_en').value,
                        flavor: document.getElementById('flavor').value,
                        flavor_en: document.getElementById('flavor_en').value,
                        // ingredients: document.getElementById('ingredients').value,
                        brand_category_id: document.getElementById('category').value,
                        text: document.getElementById('text').value,
                        color: document.getElementById('color').value,
                        index: document.getElementById('index').value,
                        // features: features,
                        link: document.getElementById('link').value,
                        related_brands: selectedbrands,
                    })
                    .then((response) => {
                        console.log('res', response);
                        if (response.status === 200) {
                            setTimeout(() => {
                                this.$router.push({path: '/panel/brand/' + this.id});
                            }, 1000);
                        }
                    })
                    .catch((error) => {
                        document.querySelector('#submit').removeAttribute('disabled');
                        document.querySelector('.loader-sm').classList.add('d-none');

                        if (error.response.status === 422) {
                            let errorList = Array(error.response.data);
                            for (var i = 0; i < errorList.length; i++) {
                                this.errors = errorList[i];
                            }

                        } else if (error.response.status === 500) {
                            if (error.response.data.message.includes("SQLSTATE")) {
                                console.error('خطای پایگاه داده');

                                function showAlertSql() {
                                    setTimeout(() => {
                                        alert(error.response.data.message);
                                    }, 200);
                                }

                                showAlertSql();
                            } else {
                                function showAlert500() {
                                    setTimeout(() => {
                                        alert(error.message + ' '
                                            + error.response.data.message);
                                    }, 200);
                                }

                                showAlert500();
                            }

                        } else {
                            function showAlert() {
                                setTimeout(() => {
                                    alert(error.message);
                                }, 200);
                            }

                            showAlert();

                        }
                    });
            }
        },

        updateData() {
            this.data.title = document.getElementById('title').value;
            this.data.title_en = document.getElementById('title_en').value;
            this.data.flavor = document.getElementById('flavor').value;
            this.data.flavor_en = document.getElementById('flavor_en').value;
            this.data.text = document.getElementById('text').value;
            this.data.brand_category_id = document.getElementById('category').value;
            this.data.color = document.getElementById('color').value;
            this.data.index = document.getElementById('index').value;
            this.data.link = document.getElementById('link').value;
        },
        watchTextAreas() {
            let txt = document.querySelector("#text");
            txt.setAttribute("style", "height:" + (txt.scrollHeight + 20) + "px;overflow-y:hidden;");
            txt.addEventListener("input", changeHeight, false);

            function changeHeight() {
                this.style.height = "auto";
                this.style.height = (this.scrollHeight) + "px";
            }
        },
        addFeature() {
            this.features.push('{"label": "", "value": "", "unit": ""}');
        },
        removeFeature(index) {

            this.features.splice(index, 1)
        },
        updateFeatures() {
            this.features = [];
            for (let i = 0; i < document.getElementsByName('featureLabel').length; i++) {
                this.features.push({
                    "label": document.getElementsByName('featureLabel')[i].value.toString(),
                    "value": document.getElementsByName('featureValue')[i].value.toString(),
                    "unit": document.getElementsByName('featureUnit')[i].value.toString()
                });
            }

        },

    }
}
</script>
<style src="@vueform/multiselect/themes/default.css"></style>

<style>
span i {
    cursor: pointer;
}

.en {
    direction: ltr !important;
    text-align: left !important;
}

.multiselect-tags-search{
    background-color: transparent !important;
}
.multiselect-tag{
    background-color: #0d6efd !important;
}
.multiselect.is-active
{
    box-shadow: none !important;
}
</style>
