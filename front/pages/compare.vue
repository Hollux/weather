<template>
    <div>
        <b-container>
            <b-form inline>
                <label>Années à comparer :</label>
                <b-form-select v-model="years[0]" :options="yearOptions" class="mx-2"
                    @change="onYearChange(0)"></b-form-select>
                <b-form-select v-model="years[1]" :options="yearOptions" class="mx-2"
                    @change="onYearChange(1)"></b-form-select>
                <b-form-select v-model="years[2]" :options="yearOptions" class="mx-2"
                    @change="onYearChange(2)"></b-form-select>
                <b-button @click="loadComparaison" variant="primary" :disabled="!areYearsValid">
                    Comparer les années complètes
                </b-button>
            </b-form>
        </b-container>

        <div class="d-flex mt-4" v-if="comparaisonData">
            <!-- NAV LATÉRALE -->
            <div class="sidebar-nav sticky-top" style="width: 250px; max-height: 80vh; overflow-y: auto;">
                <ul class="nav flex-column pt-2">
                    <li v-for="(data, metric) in comparaisonData" :key="metric" class="nav-item">
                        <a class="nav-link" :class="{ active: isActive(metric) }" @click="scrollToMetric(metric)">
                            {{ getMetricTitle(metric) }}
                        </a>
                    </li>
                </ul>
            </div>

            <!-- GRAPHIQUES -->
            <div class="flex-grow-1">
                <b-container fluid>
                    <div v-for="(data, metric) in comparaisonData" :key="metric" :id="metric"
                        class="mb-4 graph-section">
                        <h5 class="metric-title">{{ getMetricTitle(metric) }}</h5>
                        <GChart type="LineChart" :data="data.data" :options="data.options"
                            :style="{ height: '40vh', width: '100%' }" />
                    </div>
                </b-container>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        const currentYear = new Date().getFullYear();
        return {
            immutableYears: [currentYear - 3, currentYear - 2, currentYear - 1],
            years: [currentYear - 3, currentYear - 2, currentYear - 1],
            yearOptions: Array.from({ length: 30 }, (_, i) => Math.max(2000, currentYear - i)),
            comparaisonData: null,
            activeMetric: null,
        };
    },

    computed: {
        areYearsValid() {
            return this.years.filter(y => y && y >= 2000).length >= 2;
        }
    },

    methods: {
        onYearChange(changedIndex) {
            // Compte les années identiques à immutable
            let originalCount = 0;
            for (let i = 0; i < 3; i++) {
                if (this.years[i] === this.immutableYears[i]) {
                    originalCount++;
                }
            }

            // Si 2/3 sont originales → décalage automatique
            if (originalCount === 2) {
                const newValue = Math.max(2000, this.years[changedIndex]);
                const delta = newValue - this.immutableYears[changedIndex];
                this.years = this.immutableYears.map(year => Math.max(2000, year + delta));
            }
        },

        scrollToMetric(metric) {
            const element = document.getElementById(metric);
            if (element) {
                const startPosition = window.pageYOffset;
                const targetPosition = element.getBoundingClientRect().top + window.pageYOffset - 100;
                const distance = targetPosition - startPosition;
                const duration = 800; // 800ms de scroll fluide
                let startTime = null;

                const animation = (currentTime) => {
                    if (startTime === null) startTime = currentTime;
                    const timeElapsed = currentTime - startTime;
                    const progress = Math.min(timeElapsed / duration, 1);

                    // easing function pour courbe douce
                    const easeOut = 1 - Math.pow(1 - progress, 3);
                    window.scrollTo(0, startPosition + distance * easeOut);

                    if (progress < 1) {
                        requestAnimationFrame(animation);
                    } else {
                        this.activeMetric = metric;
                    }
                };

                requestAnimationFrame(animation);
            }
        },



        isActive(metric) {
            return this.activeMetric === metric;
        },

        handleScroll() {
            if (!this.comparaisonData) return;
            const sections = Array.from(document.querySelectorAll('.graph-section'));
            for (let section of sections) {
                const rect = section.getBoundingClientRect();
                if (rect.top <= 100 && rect.bottom >= 100) {
                    this.activeMetric = section.id;
                    break;
                }
            }
        },

        loadComparaison() {
            const selectedYears = this.years.filter(y => y && y >= 2000);
            const data = { years: selectedYears };

            this.$axiosPostAndInfos("getCompare", data).then((value) => {
                if (value[0] === true && value[1]) {
                    this.comparaisonData = this.formatComparaisonAnnuel(value[1], selectedYears);
                } else {
                    this.comparaisonData = null;
                }
            }).catch((error) => {
                console.error('Erreur:', error);
                this.comparaisonData = null;
            });
        },

        buildMonthlyAveragesForYear(daysArray, metric) {
            const monthlySums = {};
            daysArray.forEach(entry => {
                const date = new Date(entry.day * 1000);
                const month = date.getMonth() + 1;
                const value = Number(entry[metric]);
                if (isNaN(value)) return;
                if (!monthlySums[month]) monthlySums[month] = { sum: 0, count: 0 };
                monthlySums[month].sum += value;
                monthlySums[month].count += 1;
            });
            const monthlyAverages = {};
            for (let m = 1; m <= 12; m++) {
                const data = monthlySums[m];
                monthlyAverages[m] = data && data.count > 0 ? Number(data.sum / data.count) : 0;
            }
            return monthlyAverages;
        },

        formatComparaisonAnnuel(allData, years) {
            const result = {};
            const allMetrics = [
                'tempAvg', 'tempMax', 'tempMin', 'temp0', 'temp12',
                'pressureAvg', 'pressureMax', 'pressureMin',
                'humidityAvg', 'humidityMax', 'humidityMin',
                'uviAvg', 'uviMax',
                'windSpeedAvg', 'windSpeedMax'
            ];
            //'windDegAvg'

            const moisFR = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

            allMetrics.forEach(metric => {
                const header = ['Mois', ...years.map(year => `${metric} ${year}`)];
                const rows = [];
                for (let month = 1; month <= 12; month++) {
                    const row = [moisFR[month - 1]];
                    years.forEach(year => {
                        const yearData = Array.isArray(allData[year]) ? allData[year] : [];
                        const monthlyAverages = this.buildMonthlyAveragesForYear(yearData, metric);
                        row.push(Number(monthlyAverages[month]) || 0);
                    });
                    rows.push(row);
                }
                result[metric] = {
                    data: [header, ...rows],
                    options: this.getOptionsAnnuel(metric.replace(/Avg|Max|Min/, ''), years.length)
                };
            });
            return result;
        },

        getOptionsAnnuel(metric, seriesCount) {
            const base = {
                legend: { position: "top" },
                hAxis: { format: 'MMM', slantedTextAngle: 0 },
                vAxis: { textPosition: 'none' },
                explorer: { actions: ["dragToZoom", "rightClickToReset"] },
                curveType: "function",
                colors: ['#1565c0', '#2e7d32', '#ff9800', '#c62828'].slice(0, seriesCount)
            };

            const specifics = {
                temp: { vAxis: { minValue: -10, maxValue: 35 } },
                pressure: { vAxis: { minValue: 950, maxValue: 1050 } },
                humidity: { vAxis: { minValue: 0, maxValue: 100 } },
                uvi: { vAxis: { minValue: 0, maxValue: 12 } },
                windSpeed: { vAxis: { minValue: 0, maxValue: 50 } },
                windDeg: { vAxis: { minValue: 0, maxValue: 360 } }
            };

            const group = metric.replace(/Avg|Max|Min/, '') === 'windSpeed' ? 'windSpeed' :
                metric.replace(/Avg|Max|Min/, '') === 'windDeg' ? 'windDeg' :
                    metric.replace(/Avg|Max|Min/, '');

            return Object.assign({}, base, specifics[group] || {});
        },

        getMetricTitle(metric) {
            const titles = {
                tempAvg: "Température moyenne",
                tempMax: "Température maximale",
                tempMin: "Température minimale",
                temp0: "Température à minuit",
                temp12: "Température à midi",
                pressureAvg: "Pression moyenne",
                pressureMax: "Pression maximale",
                pressureMin: "Pression minimale",
                humidityAvg: "Humidité moyenne",
                humidityMax: "Humidité maximale",
                humidityMin: "Humidité minimale",
                uviAvg: "Indice UV moyen",
                uviMax: "Indice UV maximal",
                windSpeedAvg: "Vitesse vent moyenne",
                windSpeedMax: "Vitesse vent maximale"
            };
            //windDegAvg: "Direction vent moyenne mensuelle"
            return titles[metric] || metric;
        }
    },

    mounted() {
        window.addEventListener('scroll', this.handleScroll);
    },

    beforeDestroy() {
        window.removeEventListener('scroll', this.handleScroll);
    }
};
</script>

<style scoped>
.sidebar-nav {
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
    padding: 1rem;
    border-radius: 0.375rem;
}

.nav-link {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    color: #495057;
    cursor: pointer;
    transition: all 0.2s ease;
}

.nav-link:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.nav-link.active {
    background: #007bff;
    color: white;
}

.graph-section {
    scroll-margin-top: 100px;
}

.metric-title {
    scroll-margin-top: 80px;
}
</style>
