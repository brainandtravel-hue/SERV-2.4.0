plugins {
    id("com.android.application")
    id("com.google.gms.google-services")
}

android {
    namespace = "com.serv.android"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.serv.android"
        minSdk = 23
        targetSdk = 36
        versionCode = 2400
        versionName = "2.4.0"
    }

    buildTypes {
        release {
            isMinifyEnabled = false

            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
}

dependencies {
    implementation(
        platform("com.google.firebase:firebase-bom:34.19.0")
    )

    implementation("com.google.firebase:firebase-messaging")
}
