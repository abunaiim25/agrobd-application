pipeline {
    agent any

    environment {
        IMAGE_TAG = "${BUILD_NUMBER}"
        DOCKER_IMAGE = "mdnaiim/agrobd-app:${IMAGE_TAG}"
    }

    stages {
        // Pulls project code from Git.
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
                withCredentials([usernamePassword(credentialsId: 'dockerhub-cred',
                                  usernameVariable: 'DOCKERHUB_USER',
                                  passwordVariable: 'DOCKERHUB_PASS')]) {
                    sh """
                    echo '🔐 Logging in to DockerHub...'
                    echo "$DOCKERHUB_PASS" | docker login -u "$DOCKERHUB_USER" --password-stdin
                    docker push ${DOCKER_IMAGE}
                    docker logout
                    """
                }
            }
        }

        // Pulls/copy project code from Git
        stage('Checkout K8s Manifest Repo') {
            steps {
                git branch: 'main',
                    credentialsId: 'jenkins-github-https-cred',
                    url: 'https://github.com/abunaiim25/AgroBd-DEPLOYMENT.git'
            }
        }


        // sed -i "s#mdnaiim/agrobd-app:[0-9]*#${DOCKER_IMAGE}#g" deployment.yaml
        stage('Update K8s Manifest & Push') {
            steps {
                withCredentials([usernamePassword(credentialsId: 'jenkins-github-https-cred')]) {
                    sh """
                    echo '📝 Updating deployment.yaml with new image tag...'
                    sed -i "s#mdnaiim/agrobd-app:.*#${DOCKER_IMAGE}#g" agrobd-app/deployment.yaml

                    git config user.email "jenkins@local"
                    git config user.name "Jenkins Pipeline"
                    git add agrobd-app/deployment.yaml
                    git commit -m "Updated deployment.yaml with image tag ${IMAGE_TAG}" || echo "No changes to commit"
                    git push origin main
                    """
                }
            }
        }

    }
}
