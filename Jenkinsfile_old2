pipeline {
    agent any

    environment {
        IMAGE_TAG = "${BUILD_NUMBER}"
        DOCKER_IMAGE = "mdnaiim/agrobd-app:${BUILD_NUMBER}"
    }

    // pull/copy from git repo
    stages {
        stage('Checkout App Repo') {
            steps {
                git branch: 'main',
                    credentialsId: 'jenkins-github-https-cred',
                    url: 'https://github.com/abunaiim25/agrobd-application.git'
            }
        }

        stage('Build Docker Image') {
            steps {
                script {
                    echo "🛠 Building Docker Image..."
                    sh "docker build -t ${env.DOCKER_IMAGE} ."
                }
            }
        }

        stage('Push Docker Image to DockerHub') {
            steps {
                withCredentials([usernamePassword(credentialsId: 'dockerhub-cred',
                                  usernameVariable: 'DOCKERHUB_USER',
                                  passwordVariable: 'DOCKERHUB_PASS')]) {
                    script {
                        echo "🔐 Logging in to DockerHub..."
                        sh """
                        echo "$DOCKERHUB_PASS" | docker login -u "$DOCKERHUB_USER" --password-stdin
                        docker push ${env.DOCKER_IMAGE}
                        docker logout
                        """
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ Docker image pushed successfully: ${env.DOCKER_IMAGE}"
        }
        failure {
            echo "❌ Failed to build or push Docker image!"
        }
    }
}
