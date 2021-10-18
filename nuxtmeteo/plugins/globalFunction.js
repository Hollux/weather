import Vue from 'vue'

Vue.prototype.$axiosGet = async function (uri) {
    try {
        const serveur = await this.$axios.$get(`/api/` + uri);

        if (!serveur) {
            return;
        }
        if (serveur["warning"]) {
            serveur["warning"].forEach(element => this.$toast.show(element));
        }
        if (serveur["error"]) {
            this.$toast.error(serveur["error"]);
            return false;
        }
        if (serveur["success"]) {
            this.$toast.success(serveur["success"]);
            return true;
        }
    } catch (err) {
        this.$toast.error(
            "Erreur critique, contactez l'administrateur : " + err
        );
        console.log("error")
        return false
    }
    return true;
}

Vue.prototype.$axiosPost = async function (uri, data) {
    try {
        const serveur = await this.$axios.$post(`/api/` + uri, {
            data: data
        });
        if (!serveur) {
            return false;
        }
        if (serveur["warning"]) {
            serveur["warning"].forEach(element => this.$toast.show(element));
        }
        if (serveur["error"]) {
            this.$toast.error(serveur["error"]);
            return false;
        }
        if (serveur["success"]) {
            this.$toast.success(serveur["success"]);
            return true;
        }
    } catch (err) {
        this.$toast.error(
            "Erreur critique, contactez l'administrateur : " + err
        );
        return false
    }

    return true;
}

Vue.prototype.$axiosPostAndInfos = async function (uri, data) {
    try {
        const serveur = await this.$axios.$post(`/api/` + uri, {
            data: data
        });
        if (!serveur) {
            return;
        }
        if (serveur["warning"]) {
            serveur["warning"].forEach(element => this.$toast.show(element));
        }
        if (serveur["error"]) {
            this.$toast.error(serveur["error"]);
            return false;
        }
        if (serveur["success"]) {
            this.$toast.success(serveur["success"]);
            return [true, serveur['infos']];
        }
    } catch (err) {
        this.$toast.error(
            "Erreur critique, contactez l'administrateur : " + err
        );
        return false
    }
    return true;
}
