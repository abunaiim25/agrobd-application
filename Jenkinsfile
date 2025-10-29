pipeline {
    agent any

    environment {
        IMAGE_TAG = "${BUILD_NUMBER}"
        DOCKER_IMAGE = "mdnaiim/agrobd-app:${IMAGE_TAG}"
    }

    stages {
        // clone agrobd-application repo
        stage('Checkout App Repo') {
            steps {
                git branch: 'main',
                    credentialsId: 'jenkins-github-https-cred',
                    url: 'https://github.com/abunaiim25/agrobd-application.git'
            }
        }

        stage('Build Docker Image') {
            steps {
                sh """
                echo '🛠 Building Docker Image...'
                docker build -t ${DOCKER_IMAGE} .
                """
            }
        }

        stage('Push Docker Image to DockerHub') {
            steps {
                withCredentials([usernamePassword(credentialsId: 'dockerhub-cred')]) {
                    sh """
                    echo '🔐 Logging in to DockerHub...'
                    echo "$PASSWORD" | docker login -u "$USERNAME" --password-stdin
                    echo '🚀 Pushing Image to DockerHub...'
                    docker push ${DOCKER_IMAGE}
                    docker logout
                    """
                }
            }
        }

        // Clone AgroBd-DEPLOYMENT repo
        stage('Checkout K8s Manifest Repo') {
            steps {
                git branch: 'main',
                    credentialsId: 'jenkins-github-https-cred',
                    url: 'https://github.com/abunaiim25/AgroBd-DEPLOYMENT.git'
            }
        }

        stage('Update K8s Manifest & Push') {
            steps {
                withCredentials([usernamePassword(credentialsId: 'jenkins-github-https-cred')]) {
                    sh """
                    echo '📝 Updating deployment.yaml with new image tag...'
                    sed -i "s#mdnaiim/agrobd-app:[0-9]*#${DOCKER_IMAGE}#g" deployment.yaml

                    git config user.email "jenkins@local"
                    git config user.name "Jenkins Pipeline"
                    git add deployment.yaml
                    git commit -m "Updated deployment.yaml with image tag ${IMAGE_TAG}" || echo "No changes to commit"
                    git push origin main
                    """
                }
            }
        }
    }
}
