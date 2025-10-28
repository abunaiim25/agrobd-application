pipeline {
    agent any

    environment {
        IMAGE_TAG = "${BUILD_NUMBER}"
        DOCKER_IMAGE = "mdnaiim/agrobd-app:${IMAGE_TAG}"
    }

    stages {

        stage('Checkout App Repo') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: 'jenkins-github-https-cred',
                    usernameVariable: 'GIT_USERNAME',
                    passwordVariable: 'GIT_PASSWORD')]) {
                    sh """
                    git clone https://${GIT_USERNAME}:${GIT_PASSWORD}@github.com/abunaiim25/agrobd-application.git
                    cd agrobd-application
                    git checkout main
                    """
                }
            }
        }

        stage('Build Docker Image') {
            steps {
                dir('agrobd-application') {
                    sh """
                    echo 'Building Docker Image...'
                    docker build -t ${DOCKER_IMAGE} .
                    """
                }
            }
        }

        stage('Push Docker Image') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: 'dockerhub-cred',
                    usernameVariable: 'DOCKER_USERNAME',
                    passwordVariable: 'DOCKER_PASSWORD')]) {
                    sh """
                    echo "$DOCKER_PASSWORD" | docker login -u "$DOCKER_USERNAME" --password-stdin
                    docker push ${DOCKER_IMAGE}
                    """
                }
            }
        }

        stage('Checkout K8S Manifest Repo') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: 'jenkins-github-https-cred',
                    usernameVariable: 'GIT_USERNAME',
                    passwordVariable: 'GIT_PASSWORD')]) {
                    sh """
                    git clone https://${GIT_USERNAME}:${GIT_PASSWORD}@github.com/abunaiim25/AgroBd-DEPLOYMENT.git
                    cd AgroBd-DEPLOYMENT
                    git checkout main
                    """
                }
            }
        }

        stage('Update Manifest & Push') {
            steps {
                dir('AgroBd-DEPLOYMENT') {
                    withCredentials([usernamePassword(
                        credentialsId: 'jenkins-github-https-cred',
                        usernameVariable: 'GIT_USERNAME',
                        passwordVariable: 'GIT_PASSWORD')]) {
                        sh """
                        sed -i "s/32/${BUILD_NUMBER}/g" deployment.yaml
                        git add deployment.yaml
                        git commit -m "Updated image tag to ${BUILD_NUMBER}" || echo "No changes to commit"
                        git push https://${GIT_USERNAME}:${GIT_PASSWORD}@github.com/abunaiim25/AgroBd-DEPLOYMENT.git HEAD:main
                        """
                    }
                }
            }
        }
    }
}
